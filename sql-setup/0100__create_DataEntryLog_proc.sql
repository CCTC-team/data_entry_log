/*
    A procedure that provides paged data for viewing data entry logs

    Not every project is configured in the same way. The query needs to be able to handle projects that include
    arms, events, instances and dags, but equally needs to handle projects that don't use these optional components.

    --- event_id ---
        - can be null even for UPDATE events on object_type redcap_data e.g. when removing a record from dag
        - for filtering, use a null check to only include items where event_id is null

    --- instance ---
        - will be null when the project doesn't include repeats
        - for filtering, use -1 to get records that have no instance

    --- group_id ---
        - if not in a group, no entry for __GROUPID__ in redcap_data
        - if no entries in redcap_data with __GROUPID__ don't include in query
        - for filtering, use -1 to get records that have no group id

    --- arm ---
        - is always given with the arm_num of 1 and arm_name of 'Arm 1' even when arms not used in project
        - for filtering, only needed when more than one arm present in redcap_events_arms

    Note:
    Due to limitations with the logging, it is not possible to filter records exactly as a user may want. For example,
    filtering by form first requires the entire logging table to be processed, as it's not until that happens that the
    form becomes available. This could cause performance issues if a user tried to filter all record_ids for all dates.

    Therefore, it may be sensible to apply a limitation; either the user restricts results to a specific record id,
    or restricts the results to a limited timeframe. Alternatively, don't enforce this and if the user requests all
    record ids and all log entries for the duration of the trial, then the query may blow up the system. Probably
    enforcing one or other is the better option.

    sidenote;
        the limitation is due to a few reasons; mariadb cannot split a string by a character so handling the
        multiple sql queries in the sql_log means reverting to while loops. Also, Mariadb does not have functions that can
        return tables so you couldn't join on the result anyway. Stored procedures can return table results, but can't be
        called in a query which is what's needed to create a paged set of results
 */

create procedure GetDataEntryLogs
       (
        in skipCount int,
        in pageSize int,
        in projectId int,
        in retDirection varchar(4),
        in recordId varchar(10),
        in minDate bigint,
        in maxDate bigint,
        in dagUser varchar(100) collate utf8mb4_unicode_ci,     -- if given, must restrict to this user, if null get any
        in logUser varchar(255) collate utf8mb4_unicode_ci,
        in eventId int,
        in groupId int,
        in armNum int,
        in instance smallint(4),
        in logDescription varchar(100) collate utf8mb4_unicode_ci, -- limit of size in log event tables
        in changeReason text collate utf8mb4_unicode_ci,                -- uses 'like'
        in formName varchar(100) collate utf8mb4_unicode_ci,
        in fieldNameOrLabel varchar(100) collate utf8mb4_unicode_ci,     -- uses 'like' on either field name or label
        in newValue varchar(100) collate utf8mb4_unicode_ci,     -- uses 'like'
        -- uses not rlike excludeFieldNameRegex to exclude any fields matching the expression e.g. _monstat$|_crfver$ will exclude fields ending in _monstat and _crfver
        in excludeFieldNameRegex varchar(100) collate utf8mb4_unicode_ci
    )
begin

    /*
    -- set the defaults as mariadb doesn't support it within the arguments

    -- set a hard limit for the first query
    -- in theory, shouldn't have a negative impact as the record or time frame should fine tune the initial result set
    -- a sensible number, but in case not just hard limit it.
    -- note: this is just an arbitrary number so could be adjusted

    -- records are inclusive of times given
    -- >=  e.g. 20241007072005
    -- <=  e.g. 20241007074923

    -- the dagUser indicates whether the logs should be restricted to membership of a dag
    -- this is NOT the same as the logUser - see below for that
    -- if the user is in a dag, this will not be null. If not null restrict the data for the user
    -- use '' to represent no dag user and return everything

    -- the logUser is the user who actually created the log record as recorded in the logs table

    -- groupId
    -- any group id or specific
    -- if group id is -1 it means return records where group id is null i.e. records with no group membership
    -- php should display a list of available ones and include the name but then strip the name for the return
    -- this is closely aligned to the dagUser and only really applicable if a user is a member of multiple dags

    -- logDescription
    -- this is given by the logging automatically so will have a limited set of responses and therefore can
    -- be filtered exactly e.g. Update record, Assign record to Data Access Group, Create record

    -- changeReason
    -- the change reason should be matched as a like because it can vary considerably as is effectively free text

    -- formName
    -- the form name is only retrieved after the expensive part of the operation is completed

    -- instance
    -- if instance is -1 it means return records where instance is null i.e. records with no instance
     */

    declare hard_limit_count int;
    declare logging_table varchar(1000);
    declare datatable varchar(1000);    -- note do NOT use data_table as causes conflict
    declare mess mediumtext;
    declare sqlQuery mediumtext;

    declare usesDags int;
    declare dagSelect mediumtext;
    declare dagPart mediumtext;
    declare dagWhere mediumtext;

    declare allRowsCount int;
    declare allC int;
    declare allLogs mediumtext;
    declare queryCount int;
    declare c int;
    declare q mediumtext;
    declare parts json;

    set hard_limit_count = 10000;
    set dagSelect = '';
    set dagPart = '';
    set dagWhere = '';

    -- proj id MUST be given as module works at project level so this will return nothing if not a
    -- proper project id
    set logging_table = (select log_event_table from redcap_projects where redcap_projects.project_id = projectId);

    -- check if a valid log table found or signal error otherwise
    if logging_table is null or logging_table = '' then
        set mess = concat('The stored proc GetDataEntryLogs couldn''t find the logging table for project id: [', projectId, ']');
        signal sqlstate '45000' set message_text = mess;
    end if;

    -- direction of returned results by timestamp - either 'asc' or 'desc'
    if retDirection is null or retDirection = '' then
        set retDirection = 'desc';
    end if;

    -- if pageSize not given then default to 50
    if pageSize is null then
        set pageSize = 50;
    end if;

    -- if skip not given then default to 0
    if skipCount is null then
        set skipCount = 0;
    end if;

    -- get the data table for this project
    set dataTable = (select data_table from redcap_projects where project_id = projectId);

    -- create the temporary table for the initial selection
    drop table if exists rh_logs_init;
    create temporary table rh_logs_init
    (
        urn mediumint not null auto_increment primary key,
        ts bigint(14) DEFAULT NULL,
        sql_log mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        user varchar(255) DEFAULT NULL,
        description varchar(100)  DEFAULT NULL,
        change_reason text DEFAULT NULL,
        event_id int(10) DEFAULT NULL,
        pk varchar(200) DEFAULT NULL,
        group_id int DEFAULT NULL,
        group_name varchar(255) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    set usesDags = (select count(*) from redcap_data_access_groups where project_id = projectId);
    if usesDags > 0 then
        set dagPart =
         CONCAT(' ,(
                select distinct
                    w.project_id,
                    w.record,
                    y.username,
                    x.group_id,
                    z.group_name
                from
                    ', dataTable, ' w
                    left outer join
                    (
                        select project_id, value as group_id, record from ', dataTable, '
                        where project_id = ? and field_name = ''__GROUPID__''
                    ) x
                    on w.project_id = x.project_id
                    and w.record = x.record
                    left join
                    (
                        -- gets the group_id for the user from either source for this
                        select distinct project_id, group_id, username from redcap_data_access_groups_users
                        where project_id = ?
                        union
                        select project_id, group_id, username from redcap_user_rights
                        where project_id = ?
                        and group_id is not null
                    ) y
                    on x.project_id = y.project_id
                    and x.group_id = y.group_id
                    left join redcap_data_access_groups z
                    on y.project_id = z.project_id
                    and y.group_id = z.group_id
                where
                    w.project_id = ?

                    -- filter for dagUser
                    and ? is null or ? = y.username
            ) b ');
        set dagWhere =
            ' and a.project_id = b.project_id
            and a.pk = b.record ';
        set dagSelect =
            ', b.group_id,
            b.group_name ';
    else
        set dagSelect =
            ', null as group_id,
            null as group_name ';
    end if;

    -- get the initial logs
    set sqlQuery = concat('
        insert into rh_logs_init (ts, sql_log, user, description, change_reason, event_id, pk,
            group_id, group_name)
        select distinct
            a.ts,
            a.sql_log,
            a.user,
            a.description,
            a.change_reason,
            a.event_id,
            a.pk ', dagSelect ,
        ' from ', logging_table, ' a ',
            dagPart, '
        where
            -- make the initial filter
            a.project_id = ? ',
            dagWhere, '
            -- NOTE: this remains consistent regardless of data table used
            and a.object_type = ''redcap_data''
            and a.sql_log is not null

            -- further filters
            -- recordId
            and (? is null or a.pk = ?)
            -- minDate
            and (? is null or a.ts >= ?)
            -- maxDate
            and (? is null or a.ts <= ?)

            -- rest of filters applied below
         order by
            a.ts ', retDirection,
            -- hard limit - see note above
            ' limit ', hard_limit_count, ';');

        -- prepare and execute it
        if usesDAGs then
            -- includes dags
            prepare qry from sqlQuery;
            execute qry using
                projectId, projectId, projectId, projectId,
                dagUser, dagUser,
                projectId,
                recordId, recordId,
                minDate, minDate,
                maxDate, maxDate;
            deallocate prepare qry;
        else
            -- no dags
            prepare qry from sqlQuery;
            execute qry using
                projectId,
                recordId, recordId,
                minDate, minDate,
                maxDate, maxDate;
            deallocate prepare qry;
        end if;

    -- rh_logs_init now contains the initial selection

    -- create the table for populating the results per query
    drop table if exists rh_logs;
    create temporary table rh_logs
    (
        urn mediumint,
        ts bigint(14) DEFAULT NULL,
        sql_log mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        user varchar(255) DEFAULT NULL,
        description varchar(100) DEFAULT NULL,
        change_reason text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        event_id int(10) DEFAULT NULL,
        pk varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        group_id int DEFAULT NULL,
        group_name varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        field_name varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        instance int DEFAULT NULL,
        field_value mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- this is not ideal - have to iterate rows rather than using the usual approach, but there isn't really
    -- a choice given the limitations of mariadb where you can't run functions that return tables

    -- iterate each record in rh_logs_init and expand it so eventually contains one row per query rather than
    -- one row where queries are listed together in sql_log column

    set allRowsCount = (select count(*) from rh_logs_init);
    set allC = 1;

    -- iterate all the rows returned from the initial query
    while allC <= allRowsCount do
        set allLogs = (select sql_log from rh_logs_init where urn = allC);

        -- iterate each query in the sql_log column and create a record for it in rh_logs
        -- this will therefore expand the table providing one row for every nested query in sql_log
        set queryCount = (select round ((length(allLogs) - length( replace (
                allLogs,
                '\n',
                ''))) / length('\n')) + 1 as count);
        set c = 1;

        -- process each query
        while c <= queryCount do
            -- split the queries by new line using the c incrementer to choose each one
            set q = (rh_split_string(allLogs, '\n', c));

            -- get the parts
            set parts = (
                case left(q, 6)
                    when 'insert' then GetInsertParts(q)
                    when 'update' then GetUpdateParts(q)
                    when 'delete' then GetDeleteParts(q)
                end
            );

            -- only do the insert when the field is not record_id
            if json_value(parts, '$.fieldName') != 'record_id' then

                insert into rh_logs (urn, ts, sql_log, user, description, change_reason, event_id, pk, group_id, group_name,
                                    field_name, instance, field_value)
                select
                    urn,
                    ts,
                    q, -- this is the extracted query - can be removed
                    user,
                    description,
                    change_reason,
                    event_id,
                    pk,
                    group_id,
                    group_name,
                    json_value(parts, '$.fieldName') as field_name,
                    json_value(parts, '$.instance') as instance,
                    json_value(parts, '$.fieldValue') as field_value
                from
                    rh_logs_init
                where
                    urn = allC;

            end if;

            set c = c + 1;
        end while;

        set allC = allC + 1;
    end while;

    -- create the temporary table for the final results
    drop table if exists rh_results;
    create temporary table rh_results
    (
        ts bigint(14) DEFAULT NULL,
        log_user varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        recordId varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        group_id int DEFAULT NULL,
        group_name varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        event_id int(10) DEFAULT NULL,
        event_name varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        arm_num int(2) DEFAULT NULL,
        arm_name varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        instance smallint(4) DEFAULT NULL,
        form_name varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        field_name varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        field_label mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        value text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        change_reason text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        description varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        query_type varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        element_type varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- get the final output joining with the data table and metadata info
    -- this has to be another prepared statement as mariadb cannot use variables in a query for limit
    set sqlQuery = concat('
    insert into rh_results
        (
            ts,
            log_user,
            recordId, group_id, group_name, event_id, event_name,
            arm_num, arm_name, instance, form_name, field_name, field_label,
            value, change_reason, description, query_type, element_type
        )
    select distinct
        logs.ts,
        logs.user as log_user,
        logs.pk as recordId,
        logs.group_id,
        logs.group_name,
        meta.event_id,
        meta.event_name,
        meta.arm_num,
        meta.arm_name,
        meta.instance,
        meta.form_name,
        meta.field_name,
        meta.element_label as field_label,
        logs.field_value as value,
        logs.change_reason,
        logs.description,
        left(logs.sql_log, 6) as query_type,
        meta.element_type
    from
        rh_logs as logs
        inner join
         (
            select
                event_id,
                event_name,
                arm_name,
                arm_num,
                field_name,
                GROUP_CONCAT(value SEPARATOR "|") as value,
                instance,
                form_name,
                field_order,
                element_type,
                element_label,
                record
            from
                (select
                    a.event_id,
                    c.descrip as event_name,
                    d.arm_name,
                    d.arm_num,
                    a.field_name,
                    a.value,
                    a.instance,
                    b.form_name,
                    b.field_order,
                    b.element_type,
                    b.element_label,
                    a.record
                from
                    redcap.', dataTable, ' a
                    inner join redcap.redcap_metadata b
                    on
                    a.project_id = b.project_id
                    and a.field_name = b.field_name
                    inner join redcap.redcap_events_metadata c
                    on
                    a.event_id = c.event_id
                    inner join redcap.redcap_events_arms d
                    on
                    c.arm_id = d.arm_id
                where
                    a.project_id = ?
                ) as inner_meta
            group by
                event_id,
                event_name,
                arm_name,
                arm_num,
                field_name,
                instance,
                form_name,
                field_order,
                element_type,
                element_label,
                record
            ) as meta
        on logs.pk = meta.record
        and logs.event_id = meta.event_id
        and logs.field_name = meta.field_name
        and ((logs.instance = 0 and meta.instance is null)
                or (logs.instance = meta.instance))
    where
        -- always remove fields matching the regex of excludeFieldNameRegex
        (? is null or logs.field_name not rlike ?)

        -- eventId
        and (? is null or meta.event_id = ?)
        -- groupId
        and (? is null or logs.group_id = ? or (? = -1 and logs.group_id is null))
        -- arm num
        and (? is null or meta.arm_num = ?)
        -- logDescription
        and (? is null or logs.description = ?)
        -- changeReason
        and (? is null or logs.change_reason like concat(''%'', ?, ''%''))
        -- logUser
        and (? is null or logs.user = ?)
        -- formName
        and (? is null or meta.form_name = ?)
        -- instance
        and (? is null or meta.instance = ? or (? = -1 and meta.instance is null))
        -- field name or label
        and (? is null
            or (meta.field_name like concat(''%'', ?, ''%'') or meta.element_label like concat(''%'', ?, ''%'')))
        -- value
        and (? is null or meta.value like concat(''%'', ?, ''%''))

    order by
        -- this should preserve the timestamp order applied to logs earlier
        logs.urn;');

    prepare qry from sqlQuery;
    execute qry using
        projectId,
        excludeFieldNameRegex, excludeFieldNameRegex,
        eventId, eventId,
        groupId, groupId, groupId,
        armNum, armNum,
        logDescription, logDescription,
        changeReason, changeReason,
        logUser, logUser,
        formName, formName,
        instance, instance, instance,
        fieldNameOrLabel, fieldNameOrLabel, fieldNameOrLabel,
        newValue, newValue
        ;
    deallocate prepare qry;

    -- return total count
    select count(*) as total_count from rh_results;

    -- return distinct log_users
    select distinct user as log_user from rh_logs_init order by user;

    -- return distinct forms
    select distinct form_name from rh_results order by form_name;

    -- return distinct groups
    select distinct group_id, group_name from rh_logs_init order by group_name;

    -- return distinct events
    select distinct
        a.event_id, b.descrip as event_name
    from
        rh_logs_init a,
        redcap_events_metadata b
    where
        a.event_id = b.event_id
    order by event_id;

    -- return distinct arms
    select distinct arm_num, arm_name from rh_results order by arm_num;

    -- return distinct instances
    select distinct rh_results.instance from rh_results order by instance;

    -- return distinct descriptions
    select distinct description from rh_results order by description;

    -- return the main record set applying the paging here
    set sqlQuery = concat('select * from rh_results limit ', pageSize, ' offset ', skipCount, ';');
    prepare qry from sqlQuery;
    execute qry;
    deallocate prepare qry;

end;


/*
-- note the regex to exclude certain fields
call GetDataEntryLogs(0, 250, 16, 'desc',
                      1, null, null, null, null, null, null,
                      null, null, null, null, null,
                      null, null, '_monstat$|_crfver$|_complete$');

*/