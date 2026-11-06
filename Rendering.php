<?php

namespace CCTC\DataEntryLogModule;

use DateTime;

class Rendering {

    // makes a value safe to show as text in HTML. Values may already hold HTML entities, so they are
    // decoded first to avoid showing a literal &#039; for an apostrophe
    private static function text($value): string
    {
        return htmlspecialchars(replaceNBSP(label_decode((string)$value, false)), ENT_QUOTES | ENT_SUBSTITUTE);
    }

    public static function displayFromParts($part1, $part2): string
    {
        return $part1 == null ? "" : $part2 . " [" . $part1 . "]";
    }

    // simply renders the given data changes in a table
    public static function MakeLogTable($dataChanges, $userGrpDropDown,
                                        $singleArm, $singleEvent, $usesRepeats, $usesChangeReasons,
                                        $userDateFormat,
                                        $includeQueryType = false, $includeElementType = false): string
    {
        global $module;

        if(count($dataChanges) == 0) {
            return "No log entries found";
        }

        $projId = $module->GetProjectId();

        $ret = "
<style>#log-data-entry-event-table td { border: 1px solid #cccccc; padding: 5px; }</style>
<div style='max-width:800px;'>
<table id='log-data-entry-event-table' style='table-layout: fixed;width:90%; word-break: break-word'><tr>
    <td class='header' style='width:40px;'></td>
    <td class='header' style='width:80px;'>Date / Time</td>
    <td class='header' style='width:100px;'>Username</td>
    <td class='header' style='width:60px;'>Record ID</td>" .
        ($userGrpDropDown ? "<td class='header' style='width:100px;'>Group</td>" : "") .
        (!$singleEvent ? "<td class='header' style='width:120px;'>Event</td>" : "") .
        (!$singleArm ? "<td class='header' style='width:120px;'>Arm</td>" : "") .
        ($usesRepeats ? "<td class='header' style='width:120px;'>Instance</td>" : "") .
    "<td class='header' style='width:120px;'>Form</td>
    <td class='header' style='width:120px;'>Field and Label</td>
    <td class='header' style='width:180px;'>New Value</td>" .
        ($usesChangeReasons ? "<td class='header' style='width:180px;'>reason for change</td>" : "") .
    "<td class='header' style='width:180px;'>Action</td>" .
        ($includeQueryType ? "<td class='header' style='width:100px;'>query type</td>" : "") .
        ($includeElementType ? "<td class='header' style='width:100px;'>element type</td>" : "") .
    "</tr>";

        //get display options
        $includeEventId = $module->getProjectSetting('display-event-id-with-event-name');
        $includeArmId = $module->getProjectSetting('display-arm-id-with-arm-name');
        $includeGroupId = $module->getProjectSetting('display-dag-id-with-dag-name');

        foreach ($dataChanges as $dc) {
            $date = DateTime::createFromFormat('YmdHis', $dc->timestamp);
            $formattedDate = $date->format($userDateFormat);

            $group = self::text(
                $includeGroupId
                    ? Rendering::displayFromParts($dc->groupId, $dc->groupName)
                    : $dc->groupName);
            $event = self::text(
                $includeEventId
                    ? Rendering::displayFromParts($dc->eventId, $dc->eventName)
                    : $dc->eventName);
            $arm = self::text(
                $includeArmId
                    ? Rendering::displayFromParts($dc->armNum, $dc->armName)
                    : $dc->armName);
            $instance = strval($dc->instance) == "NULL" || strval($dc->instance) == null ? "1" : self::text($dc->instance);
            //field labels can contain formatting tags, which are dropped so only the label's text is shown.
            //a label that is only HTML, such as an image, leaves just the field name
            $fieldLabel = $dc->fieldLabel === null ? null : self::text(strip_tags2($dc->fieldLabel));
            $field = $fieldLabel === ""
                ? self::text($dc->fieldName)
                : Rendering::displayFromParts($fieldLabel, self::text($dc->fieldName));
            $newVal = nl2br(self::text(stripcslashes((string)$dc->newValue)));
            $goLink = Utility::MakeFormLink($projId, $dc->recordId, $dc->eventId, $dc->formName, $dc->fieldName, $dc->instance, "<i class='fas fa-eye'></i>");

            $ret = $ret .
            "<tr>
            <td>&nbsp;&nbsp;{$goLink}</td>
            <td>{$formattedDate}</td><td>" . self::text($dc->editor) . "</td><td>" . self::text($dc->recordId) . "</td>" .
                ($userGrpDropDown ? "<td>{$group}</td>" : "") .
                (!$singleEvent ? "<td>{$event}</td>" : "") .
                (!$singleArm ? "<td>{$arm}</td>" : "") .
                ($usesRepeats ? "<td>{$instance}</td>" : "") .
            "<td>" . self::text($dc->formName) . "</td><td>{$field}</td><td>{$newVal}</td>" .
                ($usesChangeReasons ? "<td>" . self::text($dc->reason) . "</td>" : "") .
            "<td>" . self::text($dc->description) . "</td>" .
                ($includeQueryType ? "<td>" . self::text($dc->queryType) . "</td>" : "") .
                ($includeElementType ? "<td>" . self::text($dc->elementType) . "</td>" : "") .
            "</tr>";
        }

        return $ret . "</table></div>";
    }

    public static function MakeEditorSelect($logUsers, $selected) : string
    {
        $anySelected = $selected == null ? "selected": "";
        $users = "<option value='' $anySelected>Any user</option>";;
        foreach ($logUsers as $user) {
            $sel = $selected == $user ? "selected" : "";
            $userHtml = self::text($user);
            $users .= "<option value='{$userHtml}' {$sel}>{$userHtml}</option>";
        }

        return
            "<select id='editor' name='editor' class='x-form-text x-form-field' onchange='onFilterChanged(\"editor\")'>
        {$users}
        </select>";
    }

    public static function MakeFormSelect($frms, $selected) : string
    {
        $anySelected = $selected == null ? "selected": "";
        $forms = "<option value='' $anySelected>Any form</option>";
        foreach ($frms as $frm) {
            $sel = $selected == $frm ? "selected" : "";
            $frmHtml = self::text($frm);
            $forms .= "<option value='{$frmHtml}' {$sel}>{$frmHtml}</option>";
        }

        return
            "<select id='datafrm' name='datafrm' class='x-form-text x-form-field' onchange='onFilterChanged(\"datafrm\")' style='max-width: 180px;'>
        {$forms}
        </select>";
    }

    public static function MakePageSizeSelect($pageSize) : string
    {
        $sel10 = $pageSize == 10 ? "selected" : "";
        $sel25 = $pageSize == 25 ? "selected" : "";
        $sel50 = $pageSize == 50 ? "selected" : "";
        $sel100 = $pageSize == 100 ? "selected" : "";
        $sel250 = $pageSize == 250 ? "selected" : "";
        $sel500 = $pageSize == 500 ? "selected" : "";

        return "
        <select id='pagesize' name='pagesize' class='x-form-text x-form-field' onchange='onFilterChanged(\"pagesize\")'>
            <option value='10' $sel10>10</option>
            <option value='25' $sel25>25</option>
            <option value='50' $sel50>50</option>
            <option value='100' $sel100>100</option>
            <option value='250' $sel250>250</option>
            <option value='500' $sel500>500</option>
        </select>";
    }

    public static function MakeRetDirectionSelect($dataDirection) : string
    {
        $descSel = $dataDirection == "desc" ? "selected" : "";
        $ascSel = $dataDirection == "asc" ? "selected" : "";

        return "
        <select id='retdirection' name='retdirection' class='x-form-text x-form-field' onchange='onDirectionChanged()'>
            <option value='desc' $descSel>Descending</option>
            <option value='asc' $ascSel>Ascending</option>
        </select>";
    }

    public static function MakeGroupSelect($groups, $selected, $noGroup) : string
    {
        $anySelected = $selected == null ? "selected": "";
        $grps = "<option value='' $anySelected>Any group</option>";
        foreach ($groups as $grp) {
            $id = $grp["groupId"] == null ? -1 : $grp["groupId"];
            $display = $id == -1 ? $noGroup : $grp["groupName"] . " [" . $id . "]";
            $sel = $selected == $id ? "selected" : "";
            $grps .= "<option value='" . self::text($id) . "' {$sel}>" . self::text($display) . "</option>";
        }

        return
            "<select id='datagrp' name='datagrp' class='x-form-text x-form-field' onchange='onFilterChanged(\"datagrp\")'>
        {$grps}
        </select>";
    }

    public static function MakeEventSelect($events, $selected) : string
    {
        $anySelected = $selected == null ? "selected": "";
        $evnts = "<option value='' $anySelected>Any event</option>";
        foreach ($events as $evnt) {
            $id = $evnt["eventId"];
            $display = self::displayFromParts($id, $evnt["eventName"]);
            $sel = $selected == $id ? "selected" : "";
            $evnts .= "<option value='" . self::text($id) . "' {$sel}>" . self::text($display) . "</option>";
        }

        return
            "<select id='dataevnt' name='dataevnt' class='x-form-text x-form-field' onchange='onFilterChanged(\"dataevnt\")' style='max-width: 180px;'>
        {$evnts}
        </select>";
    }

    public static function MakeArmSelect($arms, $selected, $noArm) : string
    {
        $anySelected = $selected == null ? "selected": "";
        $armies = "<option value='' $anySelected>Any arm</option>";
        foreach ($arms as $arm) {
            $id = $arm["armNum"] == null ? -1 : $arm["armNum"];
            $display = $id == -1 ? $noArm : $arm["armName"] . " [" . $id . "]";
            $sel = $selected == $id ? "selected" : "";
            $armies .= "<option value='" . self::text($id) . "' {$sel}>" . self::text($display) . "</option>";
        }

        return
            "<select id='dataarm' name='dataarm' class='x-form-text x-form-field' onchange='onFilterChanged(\"dataarm\")'>
        {$armies}
        </select>";
    }

    public static function MakeInstanceSelect($instances, $selected, $noInstance) : string
    {
        $anySelected = $selected == null ? "selected": "";
        $insts = "<option value='' $anySelected>Any instance</option>";
        foreach ($instances as $inst) {
            $id = $inst == null ? -1 : $inst;
            $display = $id == -1 ? $noInstance : $inst;
            $sel = $selected == $id ? "selected" : "";
            $insts .= "<option value='" . self::text($id) . "' {$sel}>" . self::text($display) . "</option>";
        }

        return
            "<select id='datainst' name='datainst' class='x-form-text x-form-field' onchange='onFilterChanged(\"datainst\")'>
        {$insts}
        </select>";
    }

    public static function MakeLogDescriptionSelect($logDescriptions, $selected) : string
    {
        $anySelected = $selected == null ? "selected": "";
        $descs = "<option value='' $anySelected>Any action</option>";
        foreach ($logDescriptions as $desc) {
            $sel = $selected == $desc ? "selected" : "";
            $descHtml = self::text($desc);
            $descs .= "<option value='{$descHtml}' {$sel}>{$descHtml}</option>";
        }

        return
            "<select id='logdescription' name='logdescription' class='x-form-text x-form-field' onchange='onFilterChanged(\"logdescription\")'>
        {$descs}
        </select>";
    }
}