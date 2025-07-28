create function GetDeleteParts(query mediumtext) returns mediumtext
begin

#     returns a json object
#     e.g.
#     {
#       "fieldName": "notesfld",
#       "instance": "0",
#       "fieldValue": "soemthing with ' single\r\n'\r\nquotes, \"\r\ndoubles and line \" breaks"
#     }

    declare fieldInit mediumtext;
    declare fieldName mediumtext;
    declare valueInit mediumtext;
    declare value mediumtext;
    declare removeVal mediumtext;
    declare instanceInit mediumtext;
    declare instance mediumtext;

    -- field_name
    set fieldInit = rh_split_string(query, 'AND', 4);
    set fieldName = trim(both '''' COLLATE utf8mb4_unicode_ci from trim(rh_split_string(fieldInit, '=', 2)));

    set valueInit = rh_split_string(query, 'AND', 5);
    set value = trim(both '''' COLLATE utf8mb4_unicode_ci from trim(replace(valueInit COLLATE utf8mb4_unicode_ci, 'value = ' COLLATE utf8mb4_unicode_ci, '' COLLATE utf8mb4_unicode_ci)));

    -- removing the value means can trust the split string
    set removeVal = replace(query COLLATE utf8mb4_unicode_ci, valueInit COLLATE utf8mb4_unicode_ci, '' COLLATE utf8mb4_unicode_ci);

    -- now can split using ANDAND after removing value
    set instanceInit = rh_split_string(removeVal, 'ANDAND', 2);
    set instance =
            (
                select if(lower(instanceInit) like '%null%',
                          0,
                          (select trim(replace(SUBSTRING_INDEX(instanceInit COLLATE utf8mb4_unicode_ci, "'" COLLATE utf8mb4_unicode_ci, 2), 'instance = ''' COLLATE utf8mb4_unicode_ci, '' COLLATE utf8mb4_unicode_ci)))));

    return concat('{ "fieldName" : "', fieldName, '", "instance" : ', instance, ', "fieldValue" : ', json_quote(value), '}');
end;


/*-- set @query = "DELETE FROM redcap_data WHERE project_id = 22 AND record = '1' AND event_id = 61 AND field_name = 'chkbox1' AND value = '2' AND instance is NULL LIMIT 1"
set @query = "DELETE FROM redcap_data WHERE project_id = 22 AND record = '1' AND event_id = 61 AND field_name = 'chkbox1' AND value = '3' AND instance = '2' LIMIT 1"

set @parts = (select GetDeleteParts(@query));
select @parts;
select json_valid(@parts);  -- expect 1 if valid

select json_value(@parts, '$.fieldName');
select json_value(@parts, '$.instance');
select json_value(@parts, '$.fieldValue');
*/