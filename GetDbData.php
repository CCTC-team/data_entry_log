<?php

namespace CCTC\DataEntryLogModule;

class GetDbData
{
    /**
     * Safely escapes a string value for use in SQL queries.
     * Returns 'null' (as SQL literal) if the value is null or empty, otherwise returns escaped quoted string.
     */
    private static function escapeStringParam($conn, ?string $value): string
    {
        if ($value === null || $value === "") {
            return "null";
        }
        return "'" . mysqli_real_escape_string($conn, $value) . "'";
    }

    /**
     * Safely converts a value to an integer for use in SQL queries.
     * Returns 'null' (as SQL literal) if the value is null or empty, otherwise returns the integer value.
     */
    private static function escapeIntParam($value): string
    {
        if ($value === null || $value === "" || !is_numeric($value)) {
            return "null";
        }
        return (string)(int)$value;
    }

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
            $userDag->projectId = $row['project_id'];
            $userDag->username = $row['username'];
            $userDag->groupName = $row['group_name'];
            $userDag->groupId = $row['group_id'];

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

        // Validate and sanitize retDirection - only allow 'asc' or 'desc'
        $retDirection = ($retDirection !== null && strtolower($retDirection) === 'asc') ? 'asc' : 'desc';

        // Required integer parameters - always have valid defaults
        $skipCountSafe = (string)(int)$skipCount;
        $pageSizeSafe = (string)(int)$pageSize;
        $projIdSafe = (string)(int)$projId;

        // Optional integer parameters (escapeIntParam handles null, empty, and non-numeric values)
        $eventIdSafe = self::escapeIntParam($eventId);
        $groupIdSafe = self::escapeIntParam($groupId);
        $armNumSafe = self::escapeIntParam($armNum);
        $instanceSafe = self::escapeIntParam($instance);
        $minDateSafe = self::escapeIntParam($minDate);
        $maxDateSafe = self::escapeIntParam($maxDate);

        // Safely escape string parameters
        $recordIdSafe = self::escapeStringParam($conn, $recordId);
        $dagUserSafe = self::escapeStringParam($conn, $dagUser);
        $logUserSafe = self::escapeStringParam($conn, $logUser);
        $logDescriptionSafe = self::escapeStringParam($conn, $logDescription);
        $changeReasonSafe = self::escapeStringParam($conn, $changeReason);
        $dataFormSafe = self::escapeStringParam($conn, $dataForm);
        $fieldNameOrLabelSafe = self::escapeStringParam($conn, $fieldNameOrLabel);
        $newDataValueSafe = self::escapeStringParam($conn, $newDataValue);
        $excludeFieldNameRegexSafe = self::escapeStringParam($conn, $excludeFieldNameRegex);

        $query = "call GetDataEntryLogs($skipCountSafe, $pageSizeSafe, $projIdSafe, '$retDirection', $recordIdSafe,
                $minDateSafe, $maxDateSafe, $dagUserSafe, $logUserSafe, $eventIdSafe, $groupIdSafe, $armNumSafe, $instanceSafe, $logDescriptionSafe,
                $changeReasonSafe, $dataFormSafe, $fieldNameOrLabelSafe, $newDataValueSafe, $excludeFieldNameRegexSafe);";

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
                    switch ($currentIndex) {
                        case 0: // Total count
                            while ($row = mysqli_fetch_assoc($result)) {
                                $totalCount = $row['total_count'];
                            }
                            break;

                        case 1: // Distinct log users
                            while ($row = mysqli_fetch_assoc($result)) {
                                $logUsers[] = $row['log_user'];
                            }
                            break;

                        case 2: // Distinct data forms
                            while ($row = mysqli_fetch_assoc($result)) {
                                $dataForms[] = $row['form_name'];
                            }
                            break;

                        case 3: // Distinct groups (id and name)
                            while ($row = mysqli_fetch_assoc($result)) {
                                $groups[] = [
                                    "groupId" => $row['group_id'],
                                    "groupName" => $row['group_name'],
                                ];
                            }
                            break;

                        case 4: // Distinct events (id and name)
                            while ($row = mysqli_fetch_assoc($result)) {
                                $events[] = [
                                    "eventId" => $row['event_id'],
                                    "eventName" => $row['event_name'],
                                ];
                            }
                            break;

                        case 5: // Distinct arms (num and name)
                            while ($row = mysqli_fetch_assoc($result)) {
                                $arms[] = [
                                    "armNum" => $row['arm_num'],
                                    "armName" => $row['arm_name'],
                                ];
                            }
                            break;

                        case 6: // Distinct instances
                            while ($row = mysqli_fetch_assoc($result)) {
                                $instances[] = $row['instance'];
                            }
                            break;

                        case 7: // Distinct log descriptions
                            while ($row = mysqli_fetch_assoc($result)) {
                                $logDescriptions[] = $row['description'];
                            }
                            break;

                        case 8: // Data changes (main results)
                            $dataChanges = self::GetDataChangesFromResult($result);
                            break;
                    }

                    mysqli_free_result($result);
                    $currentIndex++;
                }
            } while (mysqli_next_result($conn));
        } else {
            // Log error securely instead of displaying to user
            $module->log("GetDataEntryLogs query error: " . $conn->error);
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

        $query = "select count(*) as num_arms from redcap_events_arms where project_id = ?";

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