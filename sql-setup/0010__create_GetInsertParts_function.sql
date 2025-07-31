create function GetInsertParts(query mediumtext) returns json
begin

#     returns a json object
#     e.g.
#     {
#       "fieldName": "notesfld",
#       "instance": "0",
#       "fieldValue": "soemthing with ' single\r\n'\r\nquotes, \"\r\ndoubles and line \" breaks"
#     }

    -- get the initial chunk of the insert using regex
    declare init mediumtext;
    declare clean mediumtext;
    declare fieldPart mediumtext;
    declare fieldName mediumtext;
    declare instancePart mediumtext;
    declare instance mediumtext;
    declare value text;

    set init = regexp_substr(query, '(?s)'',\\s''.*');    
    set clean = right(init, length(init) - 3);

    -- get everything to the first , as , can't be used as field names
    set fieldPart = left(clean, instr(clean, ','));
    set fieldName = substring(fieldPart from 2 for instr(clean, ',') - 3);

    -- update the cleaned string to remove the field name to leave the following
    set clean = trim(replace(clean , fieldPart , '' ));

    -- now get the instance value
    set instancePart = reverse(left(reverse(clean), instr(reverse(clean), ',')));
    -- if instance is null then returns 0, otherwise returns the instance number
    set instance =
        (
            select if(lower(instancePart) like '%null%',
                      0,
                      (select replace(SUBSTRING_INDEX(instancePart , "'" , 2), ", '" , '' ))));

    -- update clean by removing the instance part
    set clean = trim(replace(clean , instancePart , '' ));

    -- what's left is the value
    set value = trim(both "'"  from clean );

    return concat('{ "fieldName" : "', fieldName, '", "instance" : ', instance, ', "fieldValue" : ', json_quote(value), '}');

end;

/*-- create the example query
-- set @query = "INSERT INTO redcap_data (project_id, event_id, record, field_name, value, instance) VALUES (22, 61, '1', 'notesfld', 'soemthing with \' single\r\n\'\r\nquotes, \"\r\ndoubles and line \" breaks', '3')";
set @query = "INSERT INTO redcap_data (project_id, event_id, record, field_name, value, instance) VALUES (22, 61, '1', 'notesfld', 'soemthing with \' single\r\n\'\r\nquotes, \"\r\ndoubles and line \" breaks', NULL)";


set @parts = (select GetInsertParts(@query));
select @parts
select json_valid(@parts);  -- expect 1 if valid

select json_value(@parts, '$.fieldName');
select json_value(@parts, '$.instance');
select json_value(@parts, '$.fieldValue');
*/