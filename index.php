<?php

//DO NOT use a namespace here as gives access then to root e.g. RCView, Records etc

global $module;
$modName = $module->getModuleDirectoryName();

// APP_PATH_DOCROOT = /var/www/html/redcap_v13.8.1/
require_once dirname(APP_PATH_DOCROOT, 1) . "/modules/$modName/UserDag.php";
require_once dirname(APP_PATH_DOCROOT, 1) . "/modules/$modName/DataChange.php";
require_once dirname(APP_PATH_DOCROOT, 1) . "/modules/$modName/Utility.php";
require_once dirname(APP_PATH_DOCROOT, 1) . "/modules/$modName/Rendering.php";
require_once dirname(APP_PATH_DOCROOT, 1) . "/modules/$modName/GetDbData.php";

require_once APP_PATH_DOCROOT . "/Classes/Records.php";
require_once APP_PATH_DOCROOT . "/Classes/RCView.php";
require_once APP_PATH_DOCROOT . "/Classes/DateTimeRC.php";

use CCTC\DataEntryLogModule\GetDbData;
use CCTC\DataEntryLogModule\Utility;
use CCTC\DataEntryLogModule\Rendering;

$projId = $module->getProjectId();
//there's probably a better way of getting these from $module
$moduleName = "data_entry_log";
$page = "index";
$noGroup = "-- no group --";
$noArm = "-- no arm --";
//amended with #110
//$noInstance = "-- no instance --";
$noInstance = "1";

//gets the users preferred data format which is used as data attribute on the datetimepicker field
global $datetime_format;

$userDateFormat = str_replace('y', 'Y', strtolower($datetime_format));
if(ends_with($datetime_format, "_24")){
    $userDateFormat = str_replace('_24', ' H:i', $userDateFormat);
} else {
    $userDateFormat = str_replace('_12', ' H:i a', $userDateFormat);
}

$numGroups = GetDbData::GetCountOfUserGroups();
$projUsesGroups = $numGroups > 0;
$singleEvent = GetDbData::UsesSingleEventOnly();
$singleArm = GetDbData::UsesSingleArmOnly();
$usesChangeReasons = GetDbData::UsesChangeReason();

echo "
<div class='projhdr'>
    <div style='float:left;'>
        <i class='fas fa-clipboard-list'></i> Data Entry Log
    </div>   
</div>
<br/>
<p>
    Use this page to review data changes. The options below can be used to filter the responses as required. 
</p>
";

//set the helper dates for use in the quick links
$oneDayAgo = Utility::NowAdjusted('-1 days');
$oneWeekAgo = Utility::NowAdjusted('-7 days');
$oneMonthAgo = Utility::NowAdjusted('-1 months');
$oneYearAgo = Utility::NowAdjusted('-1 years');

//get form values
$recordId = "";
if (isset($_GET['record_id'])) {
    $recordId = $_GET['record_id'];
}

$minDate = $oneWeekAgo;
if (isset($_GET['startdt'])) {
    $minDate = $_GET['startdt'];
}
$maxDate = null;
if (isset($_GET['enddt'])) {
    $maxDate = $_GET['enddt'];
}

//set the default to one week
$defaultTimeFilter = "oneweekago";
$customActive = "";
$dayActive = "";
$weekActive = "active";
$monthActive = "";
$yearActive = "";
if (isset($_GET['defaulttimefilter'])) {
    $defaultTimeFilter = $_GET['defaulttimefilter'];
    $customActive = $defaultTimeFilter == "customrange" ? "active" : "";
    $dayActive = $defaultTimeFilter == "onedayago" ? "active" : "";
    $weekActive = $defaultTimeFilter == "oneweekago" ? "active" : "";
    $monthActive = $defaultTimeFilter == "onemonthago" ? "active" : "";
    $yearActive = $defaultTimeFilter == "oneyearago" ? "active" : "";
}


$dataDirection = "desc";
if (isset($_GET['retdirection'])) {
    $dataDirection = $_GET['retdirection'];
}
$pageSize = 25;
if (isset($_GET['pagesize'])) {
    $pageSize = $_GET['pagesize'];
}
$pageNum = 0;
if (isset($_GET['pagenum'])) {
    $pageNum = $_GET['pagenum'];
}

$editor = null;
if (isset($_GET['editor'])) {
    $editor = $_GET['editor'];
}
$datafrm = null;
if (isset($_GET['datafrm'])) {
    $datafrm = $_GET['datafrm'];
}
$datagrp = null;
if (isset($_GET['datagrp'])) {
    $datagrp = $_GET['datagrp'] == $noGroup ? -1 : $_GET['datagrp'];
}
$dataevnt = null;
if (isset($_GET['dataevnt'])) {
    $dataevnt = $_GET['dataevnt'];
}
$dataarm = null;
if (isset($_GET['dataarm'])) {
    $dataarm = $_GET['dataarm'];
}
$datainstance = null;
if (isset($_GET['datainst'])) {
    $datainstance = $_GET['datainst'];
}
$fieldNameOrLabel = null;
if (isset($_GET['fldnamelbl'])) {
    $fieldNameOrLabel = $_GET['fldnamelbl'];
}
$newDataValue = null;
if (isset($_GET['newdatavalue'])) {
    $newDataValue = $_GET['newdatavalue'];
}
$logDescription = null;
if (isset($_GET['logdescription'])) {
    $logDescription = $_GET['logdescription'];
}
$changeReason = null;
if (isset($_GET['changereason'])) {
    $changeReason = $_GET['changereason'];
}

$skipCount = (int)$pageSize * (int)$pageNum;

$recordsSelect =
    Records::renderRecordListAutocompleteDropdown($projId, true, 5000,
        "record_id", "x-form-text x-form-field", "width: 150px",
        $recordId, "All records", "","submitForm('record_id')");

$minDateDb = Utility::DateStringToDbFormat($minDate);
$maxDateDb = Utility::DateStringToDbFormat($maxDate);

//get the user dag membership
$user = $module->getUser();
$username = $user->getUsername();

$userDags = GetDbData::GetUserDags(false, $username);
$dagUser = count($userDags) > 0 ? $username : null;

//check for valid min and max dates and record id
//either a specific record must be given or a maximum time of 30 days for all records

$actMinAsDate = $minDate == "" ? Utility::DefaultMinDate() : (Utility::DateStringAsDateTime($minDate) ?? Utility::DefaultMinDate());
$actMaxAsDate = $maxDate == "" ? Utility::Now() : (Utility::DateStringAsDateTime($maxDate) ?? Utility::Now());
$fixMaxDate = $actMaxAsDate > Utility::Now() ? Utility::Now() : $actMaxAsDate;

$diff = $actMaxAsDate->diff($actMinAsDate);

//get the permitted max days from config
$maxDays = $module->getProjectSetting('max-days-all-records');

//if the max days is not set, then do nothing
if (empty($maxDays)) {
    echo "<script type='text/javascript'>
            alert('Please ensure the mandatory fields in the Data Entry Log External Module are configured.');
        </script>";
    return;
}

//set default if needed
if($maxDays == null || $maxDays == ''){
    $maxDays = 31;
} elseif ($maxDays > 365) {
    $maxDays = 31;
}

// check that the record id is given, or if not max days is not greater than permitted max days
if($recordId == "" && $diff->days > (int)$maxDays)
{
    $logDataSets = GetDbData::ReturnEmptyResponse();
    $runMessage = "<div class='mt-4 mb-2'><small class='red'>The request was not run - choose all records and max window of $maxDays days or choose a record and any time window</small></div>";
} else {
    //if given, get the regex where fields should always be ignored
    $excludeFieldNameRegex = $module->getProjectSetting('always-exclude-fields-with-regex');

    //run the stored proc
    $logDataSets = GetDbData::GetDataLogsFromSP(
        $skipCount, $pageSize, $dataDirection, $recordId, $minDateDb, $maxDateDb, $dagUser,
        $editor, $dataevnt, $datagrp, $dataarm, $datainstance, $logDescription, $changeReason, $datafrm,
        $fieldNameOrLabel, $newDataValue, $excludeFieldNameRegex);

    $runMessage = "";
}

$totalCount = $logDataSets['totalCount'];
$dcs = $logDataSets['dataChanges'];
$logUsers = $logDataSets['logUsers'];

$groups = $logDataSets['groups'];
$events = $logDataSets['events'];
$arms = $logDataSets['arms'];
$instances = $logDataSets['instances'];
$usesRepeats = count($instances) > 0;

$logDescriptions = $logDataSets['logDescriptions'];
$dataForms = $logDataSets['dataForms'];
$showingCount = count($dcs);


$editorSelect = Rendering::MakeEditorSelect($logUsers, $editor);
$dataFormSelect = Rendering::MakeFormSelect($dataForms, $datafrm);
$dataGroupSelect = Rendering::MakeGroupSelect($groups, $datagrp, $noGroup);
$dataEventSelect = Rendering::MakeEventSelect($events, $dataevnt);
$dataArmSelect = Rendering::MakeArmSelect($arms, $dataarm, $noArm);
$dataInstanceSelect = Rendering::MakeInstanceSelect($instances, $datainstance, $noInstance);
$logDescriptionsSelect = Rendering::MakeLogDescriptionSelect($logDescriptions, $logDescription);
$retDirectionSelect = Rendering::MakeRetDirectionSelect($dataDirection);
$pageSizeSelect = Rendering::MakePageSizeSelect($pageSize);
$totPages = ceil($totalCount / $pageSize);
$actPage = (int)$pageNum + 1;

$skipFrom = $showingCount == 0 ? 0 : $skipCount + 1;

// adjust skipTo in cases where last page isn't a full page
if($showingCount < $pageSize) {
    $skipTo = $skipCount + $showingCount;
} else {
    $skipTo = $skipCount + (int)$pageSize;
}

$pagingInfo = "records {$skipFrom} to {$skipTo} of {$totalCount}";


//display
$userGrpDropDown = $projUsesGroups ?
    "<td><label>Group</label></td>
     <td>$dataGroupSelect</td>"
    : "";

//the event, arm and instance row should display based on the project set up - i.e. hide according to use
$evn = $singleEvent ? "<td/><td/>" :
    "<td><label>Event</label></td>
    <td>$dataEventSelect</td>";
$arm = $singleArm ? "<td/><td/>" :
    "<td><label>Arm</label></td>
     <td>$dataArmSelect</td>";
$instClass = $singleArm && $singleEvent ? "mx-0" : "mx-2";
$ins = !$usesRepeats ? "<td/><td/>" :
    "<td><label class='$instClass'>Instance</label></td>
    <td>$dataInstanceSelect</td>";

//if project uses instance and not others show that first or hide all if not used at all
if($singleArm && $singleEvent && !$usesRepeats) {
    $setupRow = "";
} elseif($singleArm && $singleEvent) {
    $setupRow = "<tr>" . $ins . "<td/>" . $arm . $evn . "</tr>";
} else {
    $setupRow = "<tr>" . $evn . "<td/>" . $arm . $ins . "</tr>";
}

//if project uses reason for change show the filter
$reasonChange = !$usesChangeReasons ? "<td/><td/>" :
    "<td><label class='mr-2'>Reason for change</label></td><td>
        <div style='display: flex; flex-direction: row'>
            <div>           
            <input id='changereason' name='changereason' type='text' size='20' maxlength='100'
                value='$changeReason' class='x-form-text x-form-field ui-autocomplete-input' autocomplete='off'
                onchange='onFilterChanged(\"changereason\")'></div>                                            
            <div><button class='clear-button' type='button' onclick='clearFilter(\"changereason\")'><small><i class='fas fa-eraser'></i></small></button></div></div></td>";

//create the reset to return to default original state
$resetUrl = APP_PATH_WEBROOT_FULL . "/ExternalModules/?prefix=$moduleName&page=$page&pid=$projId";
$doReset = "window.location.href='$resetUrl';";

echo "
<script>
    function resetForm() { 
        showProgress(1);        
        $doReset 
    }
</script>

<div class='blue' style='padding-left:8px; padding-right:8px; border-width:1px; '>    
    <form class='mt-1' id='filterForm' name='queryparams' method='get' action=''>
        <input type='hidden' id='prefix' name='prefix' value='$moduleName'>
        <input type='hidden' id='page' name='page' value='$page'>
        <input type='hidden' id='pid' name='pid' value='$projId'>
        <input type='hidden' id='totpages' name='totpages' value='$totPages'>
        <input type='hidden' id='pagenum' name='pagenum' value='$pageNum'>
        
        <input type='hidden' id='defaulttimefilter' name='defaulttimefilter' value='$defaultTimeFilter'>
        <input type='hidden' id='onedayago' name='onedayago' value='$oneDayAgo'>
        <input type='hidden' id='oneweekago' name='oneweekago' value='$oneWeekAgo'>
        <input type='hidden' id='onemonthago' name='onemonthago' value='$oneMonthAgo'>
        <input type='hidden' id='oneyearago' name='oneyearago' value='$oneYearAgo'>
                                                                    
        <table>
            <tr>
                <td style='width: 100px;'><label>Record id</label></td>
                <td style='width: 180px' >$recordsSelect</td>
                <td/><td/><td/>
            </tr>
            <tr>
                <td><label>Min edit date</label></td>
                <td><input id='startdt' style='width: 150px' name='startdt' class='x-form-text x-form-field' type='text' data-df='$userDateFormat' value='$minDate'></td>
                <td><button class='clear-button' type='button' onclick='resetDate(\"startdt\")'><small><i class='fas fa-eraser'></i></small></button></td>
                
                <td><label>Max edit date</label></td>
                <td><input id='enddt' name='enddt' class='x-form-text x-form-field' type='text' data-df='$userDateFormat' value='$maxDate'></td>
                <td><button style='margin-left: 0' class='clear-button' type='button' onclick='resetDate(\"enddt\")'><small><i class='fas fa-eraser'></i></small></button></td>
                
                <td>
                    <div class='btn-group bg-white' role='group'>                
                        <button type='button' class='btn btn-outline-primary btn-xs $customActive' onclick='setCustomRange()'>Custom range</button>
                        <button type='button' class='btn btn-outline-primary btn-xs $dayActive' onclick='setTimeFrame(\"onedayago\")'>Past day</button>
                        <button type='button' class='btn btn-outline-primary btn-xs $weekActive' onclick='setTimeFrame(\"oneweekago\")'>Past week</button>
                        <button type='button' class='btn btn-outline-primary btn-xs $monthActive' onclick='setTimeFrame(\"onemonthago\")'>Past month</button>
                        <button type='button' class='btn btn-outline-primary btn-xs $yearActive' onclick='setTimeFrame(\"oneyearago\")'>Past year</button>
                    </div>                                        
                </td>                                    
            </tr>                       
            <tr>
                <td><label>Order by</label></td>
                <td>$retDirectionSelect</td>
                <td/>
                <td><label class='mr-2'>Page size</label></td>
                <td>$pageSizeSelect</td>                
            </tr>                    
            <tr>
                <td><label>Username</label></td>
                <td>$editorSelect</td>
                <td/>                
                $userGrpDropDown
            </tr>
            $setupRow            
            <tr>
                <td><label>Form</label></td>
                <td>$dataFormSelect</td>
                <td/>
                <td><label class='mr-2'>Field name / Label</label></td>
                <td>
                    <div style='display: flex; flex-direction: row'>
                        <div><input id='fldnamelbl' name='fldnamelbl' type='text' size='20' maxlength='100'
                            value='$fieldNameOrLabel' 
                            class='x-form-text x-form-field ui-autocomplete-input' autocomplete='off'
                            onchange='onFilterChanged(\"fieldNameOrLabelChanged\")'>
                          </div>                                            
                        <div>
                            <button class='clear-button' type='button' onclick='clearFilter(\"fldnamelbl\")'><small><i class='fas fa-eraser'></i></small></button>
                        </div>
                    </div>
                </td>
                <td><label class='mx-2'>New value</label></td>
                <td>
                    <div style='display: flex; flex-direction: row'>
                        <div><input id='newdatavalue' name='newdatavalue' type='text' size='20' maxlength='100'
                            value='$newDataValue' 
                            class='x-form-text x-form-field ui-autocomplete-input' autocomplete='off'
                            onchange='onFilterChanged(\"newdatavalue\")'>
                          </div>                                            
                        <div>
                            <button class='clear-button' type='button' onclick='clearFilter(\"newdatavalue\")'><small><i class='fas fa-eraser'></i></small></button>
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <td><label>Action</label></td>
                <td>$logDescriptionsSelect</td>
                <td/>                
                $reasonChange
            </tr>            
        </table>
        <div class='p-2 mt-1' style='display: flex; flex-direction: row;'>
            <button id='btnprevpage' type='button' class='btn btn-outline-primary btn-xs mr-2' onclick='prevPage()'>
                <i class='fas fa-arrow-left fa-fw' style='font-size: medium; margin-top: 1px;'></i>
            </button>
            <button id='btnnextpage' type='button' class='btn btn-outline-primary btn-xs mr-4' onclick='nextPage()'>
                <i class='fas fa-arrow-right fa-fw' style='font-size: medium; margin-top: 1px;'></i>
            </button>     
            $pagingInfo
            <button class='clear-button' style='margin-left: 10px' type='button' onclick='resetForm()'><i class='fas fa-broom'></i> reset</button>
            <div class='ms-auto'>            
                <button class='jqbuttonmed ui-button ui-corner-all ui-widget' type='button' onclick='cleanUpParamsAndRun(\"$moduleName\", \"$projId\", \"current_page\")'>
                    <img src='" . APP_PATH_WEBROOT . "/Resources/images/xls.gif' style='position: relative;top: -1px;' alt=''>
                    Export current page
                </button>
                <button class='jqbuttonmed ui-button ui-corner-all ui-widget' type='button' onclick='cleanUpParamsAndRun(\"$moduleName\", \"$projId\", \"all_pages\")'>
                    <img src='" . APP_PATH_WEBROOT . "/Resources/images/xls.gif' style='position: relative;top: -1px;' alt=''>
                    Export all pages
                </button>
                <button class='jqbuttonmed ui-button ui-corner-all ui-widget' type='button' onclick='cleanUpParamsAndRun(\"$moduleName\", \"$projId\", \"everything\")'>
                    <img src='" . APP_PATH_WEBROOT . "/Resources/images/xls.gif' style='position: relative;top: -1px;' alt=''>
                    Export everything ignoring filters
                </button>                                    
            </div>                               
        </div>                 
    </form>
    $runMessage      
</div>
<br/>
";

echo "<script type='text/javascript'>
    function cleanUpParamsAndRun(moduleName, projId, exportType) {
        
        //construct the params from the current page params
        let finalUrl = app_path_webroot+'ExternalModules/?prefix=' + moduleName + '&page=csv_export&pid=' + projId;
        let params = new URLSearchParams(window.location.search);
        //ignore some params
        params.forEach((v, k) => {            
            if(k !== 'prefix' && k !== 'page' && k !== 'pid' && k !== 'redcap_csrf_token' ) {                
                finalUrl += '&' + k + '=' + encodeURIComponent(v);                                    
            }
        });
        
        //add the param to determine what to export        
        finalUrl += '&export_type=' + exportType;
        
        window.location.href=finalUrl;                
    }
</script>";

$table =
    Rendering::MakeLogTable($dcs, $userGrpDropDown, $singleArm, $singleEvent, $usesRepeats, $usesChangeReasons, $userDateFormat);
echo "{$table}";

?>

<style>

    #filterForm > table > tbody > tr > td:nth-child(2) {
        width: 150px;
    }

    #startdt + button, #enddt + button {
        background-color: transparent;
        border: none;
    }

    .clear-button {
        background-color: transparent;
        border: none;
        color: #0a53be;
        margin-right: 4px;
        margin-left: 4px;
        margin-top: 1px;
    }

    #log-data-entry-event-table td {
        border-width:1px;
        text-align:left;
        padding:2px 4px 2px 4px;
    }
</style>

<script>

    //fix for #104 and #106
    //gets the date format to use from the built-in format from REDCap for use with js rather than the format
    //used for $userDateFormat
    let dateFormat = user_date_format_jquery

    $('#startdt').datetimepicker({
        dateFormat: dateFormat,
        showOn: 'button', buttonImage: app_path_images+'date.png',
        onClose: function () {
            if(document.getElementById('startdt').value) {
                document.getElementById('defaulttimefilter').value = 'customrange';
                submitForm('startdt');
            }
        }
    });
    $('#enddt').datetimepicker({
        dateFormat: dateFormat,
        showOn: 'button', buttonImage: app_path_images+'date.png',
        onClose: function () {
            if(document.getElementById('enddt').value) {
                document.getElementById('defaulttimefilter').value = 'customrange';
                submitForm('enddt');
            }
        }
    });

    function setCustomRange() {
        document.getElementById('defaulttimefilter').value = 'customrange';
        document.querySelector('#startdt + button').click();
    }

    function setTimeFrame(timeframe) {
        document.getElementById('startdt').value = document.getElementById(timeframe).value;
        document.getElementById('enddt').value = '';
        document.getElementById('defaulttimefilter').value = timeframe;
        resetPaging();
        submitForm('startdt');
    }

    function resetEditor() {
        let editor = document.getElementById('editor');
        editor.value = '';
    }

    function resetDataForm() {
        let dataForm = document.getElementById('datafrm');
        dataForm.value = '';
    }

    function nextPage() {
        let currPage = document.getElementById('pagenum');
        let totPages = document.getElementById('totpages');
        if (currPage.value < totPages.value) {
            currPage.value = Number(currPage.value) + 1;
            submitForm('pagenum');
        }
    }

    function prevPage() {
        let currPage = document.getElementById('pagenum');
        if(currPage.value > 0) {
            currPage.value = Number(currPage.value) - 1;
            submitForm('pagenum');
        }
    }

    function resetPaging() {
        let currPage = document.getElementById('pagenum');
        currPage.value = 0;
        let totPages = document.getElementById('totpages');
        totPages.value = 0;
    }

    function onDirectionChanged() {
        submitForm('retdirection');
    }

    function onFilterChanged(id) {
        resetPaging();
        submitForm(id);
    }

    // use this when a field changes so can run request on any change
    function submitForm(src) {
        showProgress(1);

        let frm = document.getElementById('filterForm');
        // apply this for the record drop down to work
        let logRec = document.getElementById('record_id');
        logRec.name = 'record_id';

        //clear the csrfToken
        let csrfToken = document.querySelector('input[name="redcap_csrf_token"]');
        csrfToken.value = '';

        frm.submit();
    }

    function resetDate(dateId) {
        if(document.getElementById(dateId).value) {
            document.getElementById(dateId).value = '';
            document.getElementById('defaulttimefilter').value = 'customrange';
            submitForm(dateId);
        }
    }

    function clearFilter(id) {
        if(document.getElementById(id).value) {
            document.getElementById(id).value = '';
            submitForm(id);
        }
    }

    $(window).on('load', function() {

        //handle disabling nav buttons when not applicable
        let currPage = document.getElementById('pagenum');
        let totPages = document.getElementById('totpages');

        document.getElementById('btnprevpage').disabled = currPage.value === '0';
        document.getElementById('btnnextpage').disabled = parseInt(currPage.value) + 1 === parseInt(totPages.value);

    });

</script>
