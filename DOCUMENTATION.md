# Data Entry Log Module - Technical Documentation

## Overview

The Data Entry Log module is a REDCap External Module that provides enhanced data change logging and review capabilities for data managers. It enables filtering, searching, and exporting of data entry logs beyond REDCap's built-in logging features.

**Namespace:** `CCTC\DataEntryLogModule`
**Framework Version:** 14
**Authors:**
- Richard Hardy (University of Cambridge - Cambridge Cancer Trials Centre)
- Mintoo Xavier (Cambridge University Hospital - Cambridge Cancer Trials Centre)

## Compatibility

| Requirement | Version Range |
|-------------|---------------|
| PHP | 8.0.27 - 8.2.29 |
| REDCap | 14.7.0 - 15.9.1 |

## Features

### Core Functionality
- Display data entry changes in a tabular format with customizable columns
- Filter logs by multiple criteria simultaneously
- Pagination with configurable page sizes (10, 25, 50, 100, 250)
- Sort results in ascending or descending order by timestamp
- Export filtered results to CSV format

### Filtering Options
- **Record ID** - Filter by specific record
- **Date Range** - Min/max edit date with quick filters (past day/week/month/year)
- **Username** - Filter by the user who made the change
- **Data Access Group (DAG)** - Filter by group assignment
- **Event** - Filter by event (longitudinal projects)
- **Arm** - Filter by arm (multi-arm projects)
- **Instance** - Filter by repeating instrument instance
- **Form** - Filter by instrument/form name
- **Field Name/Label** - Text search on field names or labels
- **New Value** - Text search on the changed value
- **Action** - Filter by log description (Create record, Update record, etc.)
- **Reason for Change** - Filter by change reason (when enabled in project)

### Export Options
- **Export current page** - Export only the currently displayed records
- **Export all pages** - Export all records matching current filters
- **Export everything** - Export all records ignoring all filters

### Access Control
- Respects REDCap user rights (requires data logging permission)
- DAG-based filtering restricts users to only see logs from their assigned DAGs
- Super users can access all logs

## File Structure

```
data_entry_log_v1.0.0/
├── config.json              # Module configuration and settings
├── DataEntryLogModule.php   # Main module class with hooks
├── index.php                # Main UI page
├── GetDbData.php            # Database queries and stored procedures
├── InputValidator.php       # Input validation and sanitization
├── Rendering.php            # UI rendering utilities
├── Utility.php              # Date/time and general utilities
├── DataChange.php           # Data change model class
├── UserDag.php              # User DAG model class
├── csv_export.php           # CSV export functionality
├── getparams.php            # URL parameter handling
└── sql-setup/               # SQL stored procedures
    ├── 0000__create_helper_functions.sql
    ├── 0010__create_GetInsertParts_function.sql
    ├── 0020__create_GetUpdateParts_function.sql
    ├── 0030__create_GetDeleteParts_function.sql
    └── 0100__create_DataEntryLog_proc.sql
```

## Module Configuration

### Project Settings

| Setting | Type | Description |
|---------|------|-------------|
| `max-days-all-records` | text | Maximum days allowed (1-365) when querying all records without specifying a record ID. Required for performance optimization. |
| `always-exclude-fields-with-regex` | textarea | Regex pattern to exclude fields from logs (without leading/trailing slashes). |
| `display-event-id-with-event-name` | checkbox | Suffix event names with event ID (e.g., "Event 1 [66]"). |
| `display-arm-id-with-arm-name` | checkbox | Suffix arm names with arm ID (e.g., "Arm 1 [1]"). |
| `display-dag-id-with-dag-name` | checkbox | Suffix DAG names with DAG ID (e.g., "DAG1 [1]"). |

## Classes

### DataEntryLogModule
Main module class extending `AbstractExternalModule`.

**Public Methods:**
- `redcap_module_link_check_display($project_id, $link)` - Controls link visibility based on data logging permissions
- `validateSettings($settings)` - Validates module settings (max-days must be 1-365)
- `redcap_module_system_enable($version)` - Creates database objects on module enable
- `redcap_module_system_disable($version)` - Drops database objects on module disable

**Private Methods:**
- `exec($query)` - Executes a SQL query
- `execFromFile($file)` - Executes SQL from a file in the `sql-setup/` directory
- `dropAllDELogObjects()` - Drops all database functions and procedures
- `createAllDELogObjects()` - Creates all database objects from SQL setup files

### GetDbData
Database access layer for retrieving log data. All methods are static.

**Public Static Methods:**
- `GetDataLogsFromSP(...)` - Calls the `GetDataEntryLogs` stored procedure with filtering parameters. All user inputs are sanitized via `escapeStringParam()` and `escapeIntParam()` before use in queries. Returns an associative array with keys: `totalCount`, `logUsers`, `dataForms`, `groups`, `events`, `arms`, `instances`, `logDescriptions`, `dataChanges`
- `GetUserDags($getAll, $username)` - Retrieves DAG assignments for users
- `GetDataChangesFromResult($result)` - Converts database result into array of `DataChange` objects
- `GetCountOfUserGroups()` - Returns count of DAGs in project
- `UsesSingleEventOnly()` - Checks if project uses single event
- `UsesSingleArmOnly()` - Checks if project uses single arm
- `UsesChangeReason()` - Checks if project requires change reasons
- `ReturnEmptyResponse()` - Returns empty response array matching `GetDataLogsFromSP` structure
- `TweakVal($val, $element_type, $query_type)` - Formats checkbox values based on query type

**Private Static Methods:**
- `escapeStringParam($conn, $value)` - Escapes string values using `mysqli_real_escape_string()` for safe SQL usage
- `escapeIntParam($value)` - Casts values to integers for safe SQL usage

### InputValidator
Static utility class for input validation and sanitization of GET/POST parameters.

**Validation Methods:**
- `sanitizeString($value, $default)` - Removes null bytes and trims whitespace
- `sanitizeStringOrNull($value)` - Sanitizes string, returns `null` if empty
- `validateInt($value, $default, $min, $max)` - Validates integer within specified range
- `validateIntOrNull($value, $min, $max)` - Validates integer or returns `null`
- `validateEnum($value, $allowedValues, $default)` - Validates value against a whitelist
- `validateDateString($value, $format)` - Validates date string matches expected format
- `validateDateStringWithDefault($value, $format, $default)` - Validates date string with fallback default

**Convenience Methods (read directly from `$_GET`):**
- `getStringParam($key, $default)` - Gets sanitized string GET parameter
- `getStringParamOrNull($key)` - Gets sanitized string GET parameter or `null`
- `getIntParam($key, $default, $min, $max)` - Gets validated integer GET parameter
- `getIntParamOrNull($key, $min, $max)` - Gets validated integer GET parameter or `null`
- `getEnumParam($key, $allowedValues, $default)` - Gets validated enum GET parameter

### Rendering
UI component generation.

**Methods:**
- `MakeLogTable()` - Generates the main data table HTML
- `MakeEditorSelect()` - Creates username dropdown
- `MakeFormSelect()` - Creates form dropdown
- `MakeGroupSelect()` - Creates DAG dropdown
- `MakeEventSelect()` - Creates event dropdown
- `MakeArmSelect()` - Creates arm dropdown
- `MakeInstanceSelect()` - Creates instance dropdown
- `MakeLogDescriptionSelect()` - Creates action dropdown
- `MakePageSizeSelect()` - Creates page size dropdown
- `MakeRetDirectionSelect()` - Creates sort direction dropdown

### Utility
Static utility class for formatting, date/time handling, and links.

**Constants:**
- `DEFAULT_MIN_DATE_YEARS_AGO = 5` - Number of years to look back for the default minimum date filter

**Methods:**
- `MakeFormLink($baseUrl, $projectId, $recordId, $eventId, $formName, $fldName, $instance, $val)` - Creates HTML link to navigate to specific form/field
- `groupBy($array, $function)` - Groups array elements by key returned from callback function
- `UserDateFormat()` - Gets user's preferred date format
- `UserDateTimeFormat()` - Gets user's preferred datetime format with seconds
- `UserDateTimeFormatNoSeconds()` - Gets user's preferred datetime format without seconds
- `FullDateTimeInUserFormatAsString($d)` - Formats DateTime in user's preferred format with seconds
- `DateTimeNoSecondsInUserFormatAsString($d)` - Formats DateTime without seconds
- `Now()` - Returns current DateTime
- `NowInUserFormatAsString()` - Returns current time in user's preferred format
- `NowInUserFormatAsStringNoSeconds()` - Returns current time without seconds
- `NowAdjusted($modifier)` - Returns current datetime adjusted by modifier (e.g., "-5 days")
- `DefaultMinDate()` - Returns default minimum date (calculated as 5 years ago)
- `DefaultMinDateInUserFormatAsString()` - Returns default minimum date in user's preferred format
- `DateStringAsDateTime($date, $format)` - Parses date string to DateTime object
- `DateStringToDbFormat($date)` - Converts date string to database format (`YmdHis`)

### DataChange
Data model representing a single log entry. All properties are nullable with typed declarations.

**Properties:**
- `?string $recordId`, `?int $eventId`, `?string $eventName`, `?int $instance`
- `?string $timestamp`, `?string $editor`, `?string $formName`
- `?string $fieldName`, `?string $fieldLabel`, `?string $newValue`
- `?string $reason`, `?string $armName`, `?int $armNum`
- `?string $queryType`, `?string $elementType`
- `?int $groupId`, `?string $groupName`, `?string $description`

### UserDag
Data model for user DAG assignments. All properties use camelCase naming with typed declarations.

**Properties:**
- `?int $groupId` - Data access group ID
- `?int $projectId` - Project ID
- `?string $groupName` - Data access group name
- `?string $username` - Username

## Database Objects

The module creates the following database objects on enable:

### Functions
- `rh_split_string` - String splitting helper
- `GetInsertParts` - Parses INSERT log entries
- `GetUpdateParts` - Parses UPDATE log entries
- `GetDeleteParts` - Parses DELETE log entries

### Stored Procedure
- `GetDataEntryLogs` - Main procedure for retrieving filtered log data

## URL Parameters

The module accepts the following GET parameters. All parameters are validated and sanitized via `InputValidator` before use.

| Parameter | Type | Description | Validation |
|-----------|------|-------------|------------|
| `record_id` | string | Filter by record ID | Sanitized string |
| `startdt` | string | Minimum edit date | Validated against user's date format |
| `enddt` | string | Maximum edit date | Validated against user's date format |
| `defaulttimefilter` | enum | Quick time filter | Allowed: `onedayago`, `oneweekago`, `onemonthago`, `oneyearago`, `customrange` |
| `retdirection` | enum | Sort direction | Allowed: `asc`, `desc` |
| `pagesize` | int | Number of records per page | Allowed: 10, 25, 50, 100, 250, 500 |
| `pagenum` | int | Current page number (0-indexed) | Validated integer with range constraints |
| `editor` | string | Filter by username | Sanitized string |
| `datagrp` | int | Filter by DAG ID | Validated integer |
| `dataevnt` | int | Filter by event ID | Validated integer |
| `dataarm` | int | Filter by arm number | Validated integer |
| `datainst` | int | Filter by instance number | Validated integer |
| `datafrm` | string | Filter by form name | Sanitized string |
| `fldnamelbl` | string | Filter by field name or label | Sanitized string |
| `newdatavalue` | string | Filter by new value | Sanitized string |
| `logdescription` | string | Filter by action type | Sanitized string |
| `changereason` | string | Filter by reason for change | Sanitized string |

## Security

### Input Validation
All GET parameters are validated and sanitized through the `InputValidator` class before being used. This includes:
- **String sanitization** - Null byte removal and whitespace trimming for all string inputs
- **Integer validation** - Type-casting and range checking for numeric parameters
- **Enum validation** - Whitelist checks for parameters with fixed allowed values (e.g., `retdirection`, `defaulttimefilter`, `pagesize`)
- **Date validation** - Format validation against the user's configured date format

### SQL Injection Prevention
All user-supplied values are escaped before being used in SQL queries:
- String values are escaped using `mysqli_real_escape_string()` via `GetDbData::escapeStringParam()`
- Integer values are type-cast via `GetDbData::escapeIntParam()`
- The `retDirection` parameter is validated to only allow `asc` or `desc`

### Error Handling
Database errors are logged to REDCap's module log via `$module->log()` instead of being displayed to users, preventing information disclosure.

### CSV Export Validation
The `export_type` parameter is validated against allowed values: `current_page`, `all_pages`, `everything`.

## Usage Notes

1. **Performance:** When querying all records (no specific record ID), the query is limited by `max-days-all-records` setting to prevent timeout issues on large projects.

2. **DAG Restrictions:** Users assigned to DAGs will only see logs for records within their assigned DAGs.

3. **Checkbox Fields:** Checkbox field changes are displayed as "item[value] checked" or "item[value] unchecked" for clarity.

4. **Date Format:** The module respects the user's preferred date/time format configured in REDCap.

5. **Field Exclusion:** Use the `always-exclude-fields-with-regex` setting to hide sensitive or irrelevant fields from the logs.

6. **Default Date Range:** When no minimum date is specified, the module defaults to 5 years ago (configurable via `Utility::DEFAULT_MIN_DATE_YEARS_AGO`).

7. **Export Limits:** CSV exports are capped at 1,000,000 records (`MAX_EXPORT_RECORDS` constant in `csv_export.php`).

## Hooks Used

- `redcap_module_link_check_display` - Controls project menu link visibility
- `redcap_module_system_enable` - Creates database objects
- `redcap_module_system_disable` - Removes database objects
