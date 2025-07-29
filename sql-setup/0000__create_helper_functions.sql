create function rh_split_string(str mediumtext, delim varchar(12), pos int)
returns mediumtext
return replace(substring(substring_index(str, delim, pos),
       char_length(substring_index(str, delim, pos-1)) + 1),
       delim, '')
;