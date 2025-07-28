fixes to make this possible

- escape the special chars for saving in the log 

DataEntry.php line 5652
```
if ($field_name != "__GROUPID__" && $field_name != '__LOCKRECORD__') {
    //RH orig
    //$display[] = "$field_name = '$value' xxx";
    $escValue = addslashes($value);
    $display[] = "$field_name = '$escValue'";
}
```

this will escape all special chars

so this

<pre>
fld1 = 'frm1 fld1 AA',
fld2 = 'frm1 fld2 BB',
notesfld = 'these , are again
some 'lovely'
notes
'on',
many 
lines CC',
intfld = '1'

becomes this

<pre>
notesfld = 'these , are again
some \'lovely\'
notesss
\'on\',
many 
lines CC'