<?php

namespace CCTC\DataEntryLogModule;

class GetDbData
{

    // returns all the dags for the current project and user
    // if the username is not given, just assumes the current user
    public static function GetUserDags(bool $getAll, ?string $username): array
    {
        global $module;

        $projId = $module->getProjectId();

        // if getting for a specific user set the username for current user if not given
        if(!$getAll && $username == null)
        {
            $user = $module->getUser();
            $username = $user->getUsername();
        }

        $getForAllSql = "";
        if(!$getAll) {
            $getForAllSql = "and u.username = ?";
        }

        $query = "
        select 
            g.*, u.username 
        from 
            redcap_data_access_groups g, 
            (
                select project_id, group_id, username from redcap_data_access_groups_users
                union
                select project_id, group_id, username from redcap_user_rights
            )  u
        where
            g.group_id = u.group_id 
            and u.project_id = g.project_id 
            and g.project_id = ?
            " . $getForAllSql . ";";

        // add the username param if needed
        $p = [$projId];
        if(!$getAll) {
            $p[] = $username;
        }
        $result = $module->query($query, $p);

        $userDags = array();

        while ($row = db_fetch_assoc($result))
        {
            $userDag = new UserDag();
            $userDag->project_id = $row['project_id'];
            $userDag->username = $row['username'];
            $userDag->group_name = $row['group_name'];
            $userDag->group_id = $row['group_id'];

            $userDags[] = $userDag;
        }

        return $userDags;
    }

    static function TweakVal($val, $element_type, $query_type): string {
        if($element_type == "checkbox") {
            if($query_type == "INSERT") {
                return "item[{$val}] checked";
            }
            if($query_type == "DELETE") {
                return "item[{$val}] unchecked";
            }
        }

        return $val == null ? '' : $val;
    }

    static function GetDataChangesFromResult($result) : array
    {
        $dataChanges = array();

        while ($row = db_fetch_assoc($result))
        {
            $dc = new DataChange();
            $dc->timestamp = $row['ts'];
            $dc->editor = $row['log_user'];
            $dc->recordId = $row['recordId'];
            $dc->groupId = $row['group_id'];
            $dc->groupName = $row['group_name'];
            $dc->eventId = $row['event_id'];
            $dc->eventName = $row['event_name'];
            $dc->armNum = $row['arm_num'];
            $dc->armName = $row['arm_name'];
            $dc->instance = $row['instance'];
            $dc->formName = $row['form_name'];
            $dc->fieldName = $row['field_name'];
            $dc->fieldLabel = $row['field_label'];
            $dc->newValue = self::TweakVal($row['value'], $row['element_type'], $row['query_type']);
            $dc->reason = $row['change_reason'];
            $dc->description = $row['description'];
            $dc->queryType = $row['query_type'];
            $dc->elementType = $row['element_type'];

            $dataChanges[] = $dc;
        }

        return $dataChanges;
    }

    // calls the GetDataEntryLogs stored procedure with the given parameters and returns the relevant data
    public static function GetDataLogsFromSP(
        $skipCount, $pageSize, $retDirection, $recordId, $minDate, $maxDate,
        $dagUser, $logUser, $eventId, $groupId, $armNum, $instance, $logDescription, $changeReason,
        $dataForm, $fieldNameOrLabel, $newDataValue, $excludeFieldNameRegex)
    : array
    {
        /*
            Note: the dagUser is used to ensure only records from the users DAG are returned. If this is null, all
                records are returned. The logUser relates to the user who made the entry in the log as documented in
                the log table. This can be filtered against in the UI. The dagUser is applied irrespective of filtering
                as logs from a dag for which the user is not a member should never be returned.
         */

        global $module;
        global $conn;

        $projId = $module->getProjectId();
        $retDirection = $retDirection == null ? "desc" : $retDirection;
        $recordId = $recordId == null ? "null" : "'$recordId'";
        $minDate = $minDate == null ? "null" : $minDate;
        $maxDate = $maxDate == null ? "null" : $maxDate;
        $dagUser = $dagUser == null ? "null" : "'$dagUser'";
        $logUser = $logUser == null ? "null" : "'$logUser'";
        $eventId = $eventId == null ? "null" : $eventId;
        $groupId = $groupId == null ? "null" : $groupId;
        $armNum = $armNum == null ? "null" : $armNum;
        $instance = $instance == null ? "null" : $instance;
        $logDescription = $logDescription == null ? "null" : "'$logDescription'";
        $changeReason = $changeReason == null ? "null" : "'$changeReason'";
        $dataForm = $dataForm == null ? "null" : "'$dataForm'";
        $fieldNameOrLabel = $fieldNameOrLabel == null ? "null" : "'$fieldNameOrLabel'";
        $newDataValue = $newDataValue == null ? "null" : "'$newDataValue'";
        $excludeFieldNameRegex = $excludeFieldNameRegex == null ? "null" : "'$excludeFieldNameRegex'";


        $query = "call GetDataEntryLogs($skipCount, $pageSize, $projId, '$retDirection', $recordId, 
                $minDate, $maxDate, $dagUser, $logUser, $eventId, $groupId, $armNum, $instance, $logDescription, 
                $changeReason, $dataForm, $fieldNameOrLabel, $newDataValue, $excludeFieldNameRegex);";

        $currentIndex = 0;

        $totalCount = 0;
        $logUsers = array();
        $dataForms = array();
        $groups = array();
        $events = array();
        $arms = array();
        $instances = array();
        $logDescriptions = array();
        $dataChanges = array();

        if (mysqli_multi_query($conn, $query)) {
            do {
                if ($result = mysqli_store_result($conn)) {

                    //total number of records
                    if($currentIndex == 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $totalCount = $row['total_count'];
                        }
                    }

                    //the distinct list of log users in the result set
                    if($currentIndex == 1) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $logUsers[] = $row['log_user'];
                        }
                    }

                    //get the distinct list of data forms
                    if($currentIndex == 2) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $dataForms[] = $row['form_name'];
                        }
                    }

                    //get the distinct list of groups (group id and name)
                    if($currentIndex == 3) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $groups[] =
                                [
                                    "groupId" => $row['group_id'],
                                    "groupName" => $row['group_name'],
                                ];
                        }
                    }

                    //get the distinct list of events (event id and name)
                    if($currentIndex == 4) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $events[] =
                                [
                                    "eventId" => $row['event_id'],
                                    "eventName" => $row['event_name'],
                                ];
                        }
                    }

                    //get the distinct list of arms (arm num and name)
                    if($currentIndex == 5) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $arms[] =
                                [
                                    "armNum" => $row['arm_num'],
                                    "armName" => $row['arm_name'],
                                ];
                        }
                    }

                    //get the distinct list of instances
                    if($currentIndex == 6) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $instances[] = $row['instance'];
                        }
                    }

                    //get the distinct list of descriptions for log
                    if($currentIndex == 7) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $logDescriptions[] = $row['description'];
                        }
                    }

                    //the results
                    if($currentIndex == 8) {
                        $dataChanges = self::GetDataChangesFromResult($result);
                    }

                    mysqli_free_result($result);

                    $currentIndex++;
                }
            } while (mysqli_next_result($conn));
        } else {
            echo "Error: " . $conn->error;
        }

        //NOTE: changes here require changes in ReturnEmptyResponse
        return
            [
                "totalCount" => $totalCount,
                "logUsers" => $logUsers,
                "dataForms" => $dataForms,
                "groups" => $groups,
                "events" => $events,
                "arms" => $arms,
                "instances" => $instances,
                "logDescriptions" => $logDescriptions,
                "dataChanges" => $dataChanges
            ];
    }


    //returns an empty response as the sp was not run
    public static function ReturnEmptyResponse() : array
    {
        //NOTE: changes here require changes in GetDataLogsFromSP
        return
            [
                "totalCount" => 0,
                "logUsers" => [],
                "dataForms" => [],
                "groups" => [],
                "events" => [],
                "arms" => [],
                "instances" => [],
                "logDescriptions" => [],
                "dataChanges" => []
            ];
    }

    //returns the number of groups used by the project
    public static function GetCountOfUserGroups(): int
    {
        global $module;

        $projId = $module->getProjectId();

        $query = "select count(*) numGroups from redcap_data_access_groups where project_id = ?;";
        $result = $module->query($query, [ $projId ]);

        $numGroups = 0;

        while ($row = db_fetch_assoc($result))
        {
            $numGroups = $row['numGroups'];
        }

        return $numGroups;
    }

    // returns true if only a single event is used in the project
    public static function UsesSingleEventOnly(): bool
    {
        global $module;
        $projId = $module->getProjectId();
        $logTable = \Logging::getLogEventTable($projId);

        $query = "            
            select distinct
                a.event_id,
                b.descrip as event_name
            from
                " . $logTable ." a
                inner join redcap_events_metadata b
                    on a.event_id = b.event_id
            where
                a.project_id = ?;
        ";

        $result = $module->query($query, [ $projId ]);
        $events = array();

        while ($row = db_fetch_assoc($result))
        {
            $events[] = $row['event_name'];
        }

        return count($events) == 1;
    }

    //returns true if the project just uses one arm
    public static function UsesSingleArmOnly(): bool
    {
        global $module;
        $projId = $module->getProjectId();

        $query = "select count(*) as num_arms from redcap.redcap_events_arms where project_id = ?";

        $result = $module->query($query, [ $projId ]);
        $numArms = 0;

        while ($row = db_fetch_assoc($result))
        {
            $numArms = $row['num_arms'];
        }

        return $numArms == 1;
    }

    //returns true if the project uses the reasons for change functionality
    public static function UsesChangeReason(): bool
    {
        global $module;
        $projId = $module->getProjectId();

        $query = "select require_change_reason from redcap_projects where project_id = ?";
        $result = $module->query($query, [ $projId ]);
        $usesChangeReasons = -1;

        while ($row = db_fetch_assoc($result))
        {
            $usesChangeReasons = $row['require_change_reason'];
        }

        return $usesChangeReasons == 1;
    }
}