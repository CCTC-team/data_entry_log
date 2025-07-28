create function rh_split_string(str mediumtext collate utf8mb4_unicode_ci, delim varchar(12) collate utf8mb4_unicode_ci, pos int)
returns mediumtext
return replace(substring(substring_index(str, delim, pos),
       char_length(substring_index(str, delim, pos-1)) + 1),
       delim, '')
;