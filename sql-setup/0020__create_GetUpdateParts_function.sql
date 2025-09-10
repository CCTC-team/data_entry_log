create function GetUpdateParts(query mediumtext) returns json
begin

#     returns a json object
#     e.g.
#     {
#       "fieldName": "notesfld",
#       "instance": "0",
#       "fieldValue": "something with ' single\r\n'\r\nquotes, \"\r\ndoubles and line \" breaks"
#     }

    declare init mediumtext;
    declare valOnwards mediumtext;
    declare valQuoted mediumtext;
    declare value mediumtext;
    declare rest mediumtext;
    declare fieldName mediumtext;
    declare instanceInit mediumtext;
    declare instance mediumtext;

    set init = rh_split_string(query, '''', 1);
    set valOnwards = replace(query, init, '');
    set valQuoted = regexp_substr(valOnwards, "(?s)^'(.*?)(?<!')'(?!')");

    -- get value
    set value = trim(both '''' from valQuoted);

    -- rest except value
    set rest = replace(query, concat(init, valQuoted), '');

    -- fix added to resolve issue #108 - previously the assumption was that the field_name is at position 4
    -- however, that's not true so need to iterate to find it
    set @i = 1;
    set @maxI = 10;
    set @field = '';

    WHILE @i <= @maxI DO
        -- Get the current split value
        set @field = rh_split_string(rest, 'AND', @i);

        -- Check if the value starts with 'field_name'
        IF LEFT(LTRIM(@field), 10) = 'field_name' THEN
            -- kill the loop
            set @i = 10;
        END IF;

        -- Increment the index
        SET @i = @i + 1;
    END WHILE;

    -- field name
    set fieldName = trim(both '''' from trim(replace(rh_split_string(@field, 'AND', 1), 'field_name = ' COLLATE utf8mb4_unicode_ci, '')));

    -- get the full instance part first
    set instanceInit = trim(';' COLLATE utf8mb4_unicode_ci from rh_split_string(rest, 'AND', 5));

    -- handle if null
    if instanceInit like '%NULL' or instanceInit like '%NULL%' then
        set instance = 1;
    else
        set instance = trim(both '''' from trim(replace(instanceInit, 'instance = ', '')));
    end if;

    return concat('{ "fieldName" : "', fieldName, '", "instance" : ', instance, ', "fieldValue" : ', json_quote(value), '}');

end;


/*set @query = "UPDATE redcap_data SET value = 'these , are again\r\nsome \''lovely\''\r\nnotesss\r\n\''on\'', with double \"\r\n\" here too\r\nmany \r\nlines CC' WHERE project_id = 22 AND record = '1' AND event_id = 61 AND field_name = 'intfldxxx' AND instance = '3';";
set @query = "UPDATE redcap_data SET value = 'these , are again\r\nsome \''lovely\''\r\nnotesss\r\n\''on\'', with double \"\r\n\" here too\r\nmany \r\nlines CC' WHERE project_id = 22 AND record = '1' AND event_id = 61 AND field_name = 'intfldxxx' AND instance = '3'";
set @query = "UPDATE redcap_data SET value = 'these , are again\r\nsome \''lovely\''\r\nnotesss\r\n\''on\'', with double \"\r\n\" here too\r\nmany \r\nlines CC' WHERE project_id = 22 AND record = '1' AND event_id = 61 AND field_name = 'intfld' AND instance is NULL;";
set @query = "UPDATE redcap_data SET value = 'these , are again\r\nsome \''lovely\''\r\nnotesss\r\n\''on\'', with double \"\r\n\" here too\r\nmany \r\nlines CC' WHERE project_id = 22 AND record = '1' AND event_id = 61 AND field_name = 'intfld' AND instance is NULL";


set @parts = (select GetUpdateParts(@query));
select @parts

select json_valid(@parts);  -- expect 1 if valid
select json_value(@parts, '$.fieldName');
select json_value(@parts, '$.instance');
select json_value(@parts, '$.fieldValue');*/