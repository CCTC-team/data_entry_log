<?php

namespace CCTC\DataEntryLogModule;

use REDCap;
use ExternalModules\AbstractExternalModule;

class DataEntryLogModule extends AbstractExternalModule {

    /**
     * Check whether the user has permission to view logs and deny access if not.
     * Uses getUser() to properly handle impersonated users.
     */
    public function redcap_module_link_check_display($project_id, $link)
    {
        $user = $this->getUser();
        $rights = $user->getRights();
        if($rights['data_logging']) {
            return $link;
        } else {
            return 0;
        }
    }

    public function validateSettings($settings): ?string
    {
        if (array_key_exists("max-days-all-records", $settings) && !empty($settings['max-days-all-records'])) {
            if(!is_numeric($settings['max-days-all-records']) || $settings['max-days-all-records'] < 1 || $settings['max-days-all-records'] > 365) {
                return "The maximum number of days permitted should be a number between 1 and 365";
            }
        }

        return null;
    }

    private function exec($query): void
    {
        db_query($query);
    }

    private function execFromFile($file): void
    {
        $sql = file_get_contents(dirname(__FILE__) . "/sql-setup/$file");
        db_query($sql);
    }

    public function redcap_module_system_enable($version): void
    {
        $this->log('Module system enable initiated', ['version' => $version]);
        $this->dropAllDELogObjects();
        $this->createAllDELogObjects();
        $this->log('Database objects created successfully');
    }

    public function redcap_module_system_disable($version): void
    {
        $this->log('Module system disable initiated', ['version' => $version]);
        $this->dropAllDELogObjects();
        $this->log('Database objects dropped successfully');
    }

    /**
     * Drops all database objects associated with the data entry log module.
     */
    private function dropAllDELogObjects(): void
    {
        $this->exec("drop function if exists rh_split_string;");
        $this->exec("drop function if exists GetInsertParts;");
        $this->exec("drop function if exists GetUpdateParts;");
        $this->exec("drop function if exists GetDeleteParts;");
        $this->exec("drop procedure if exists GetDataEntryLogs;");
    }

    /**
     * Creates all database objects required by the data entry log module.
     */
    private function createAllDELogObjects(): void
    {
        $this->execFromFile("0000__create_helper_functions.sql");
        $this->execFromFile("0010__create_GetInsertParts_function.sql");
        $this->execFromFile("0020__create_GetUpdateParts_function.sql");
        $this->execFromFile("0030__create_GetDeleteParts_function.sql");
        $this->execFromFile("0100__create_DataEntryLog_proc.sql");
    }

    public function redcap_module_save_configuration($project_id): void
    {
        $this->auditConfigurationChange($project_id);
    }

    private function auditConfigurationChange($project_id): void
    {
        $config   = $this->getConfig();
        $isSystem = empty($project_id);
        $scope    = $isSystem ? 'system' : 'project';
        $keys     = $this->collectSettingKeys($config[$scope . '-settings'] ?? []);
        if (empty($keys)) return;

        $snapshotKey = 'audit-snapshot-' . $scope;
        $read  = fn($k)     => $isSystem ? $this->getSystemSetting($k)    : $this->getProjectSetting($k);
        $write = fn($k, $v) => $isSystem ? $this->setSystemSetting($k, $v) : $this->setProjectSetting($k, $v);

        $new = [];
        foreach ($keys as $k) $new[$k] = $this->normaliseSetting($read($k));

        $rawOld = $read($snapshotKey);
        $old = is_string($rawOld) ? json_decode($rawOld, true) : null;
        // First save has no prior snapshot: treat the baseline as empty so the
        // initial configuration's real values are still logged ((empty) -> value),
        // while settings left blank stay '' vs '' and produce no noise.
        if (!is_array($old)) $old = [];

        $changed = false;
        foreach ($keys as $k) {
            $before = $this->normaliseSetting($old[$k] ?? null);
            $after  = $new[$k];
            if ($before !== $after) {
                $changed = true;
                $this->log("Configuration changed ($scope)", [
                    'project_id' => $project_id,
                    'setting'    => $k,
                    'old_value'  => $before === '' ? '(empty)' : $before,
                    'new_value'  => $after  === '' ? '(empty)' : $after,
                ]);
            }
        }
        if ($changed) $write($snapshotKey, json_encode($new));
    }

    private function collectSettingKeys(array $settings): array
    {
        $keys = [];
        foreach ($settings as $s) {
            if (!isset($s['key'])) continue;
            if (($s['type'] ?? '') === 'descriptive') continue;
            $keys[] = $s['key'];
        }
        return $keys;
    }

    private function normaliseSetting($v): string
    {
        if ($v === null || $v === false) return '';
        if ($v === true) return '1';
        if (is_array($v)) {
            // A repeatable setting the admin never filled comes back as an array
            // of empty entries (e.g. [null]), not as null. Treat that as unset,
            // otherwise the first save logs a phantom "(empty) -> [null]" change
            // for every blank repeatable.
            foreach ($v as $entry) {
                $hasValue = is_array($entry) ? !empty($entry) : trim((string) ($entry ?? '')) !== '';
                if ($hasValue) return json_encode($v);
            }
            return '';
        }
        return trim((string) $v);
    }
}
