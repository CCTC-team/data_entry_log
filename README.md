### Data Entry Log

[![Data Entry Log EM Cypress Tests](https://github.com/CCTC-team/data_entry_log/actions/workflows/cypress-tests.yml/badge.svg)](https://github.com/CCTC-team/data_entry_log/actions/workflows/cypress-tests.yml)

The Data Entry log is designed to help users review and query data entered within a project. The existing Logging
feature provides limited help to Data managers who need to review recently entered data and may, for example, wish to
filter changes made to a form or field level.

This module simply accesses logs creating by the vanilla system and interrogates the log records sql statements. If 
there are no logs (for instance in a project that has been imported from xml with data), the module will be of limited
use. The module shows records for the user's current DAG only and does not support users assigned to multiple DAGs.

#### System set up

> **Note:** This module has been tested with **MariaDB versions 10.5 and 10.11**. Please verify that the module works correctly with your specific database version before deploying to a production environment.

Enabling the module at a system level will automatically create some functions and procedures as required;
1. Create the `GetDataEntryLogs` stored procedure in the REDCap database. This procedure is required to provide the log
   of data entries
1. Create the function `rh_split_string`, a utility function used by other procedures and functions
1. Create the function `GetInsertParts`, a function used to extract the inserts from the sql log entries
1. Create the function `GetUpdateParts`, a function used to extract the updates from the sql log entries
1. Create the function `GetDeleteParts`, a function used to extract the deletes from the sql log entries

Disabling the module at a system level will automatically drop the stored procedure and all functions as listed above.

When a new version of the module becomes available, the module should be disabled and then re-enabled from the Control Center at the system level. Failure to do so may cause the module to malfunction.

The following project level settings are available;

- `max-days-all-records` - the maximum number of days available to view in the log when viewing all records. Defaults
  to 31 if not configured. The performance impact of the query increases with the number. Adjust the value to consider
  the impact. A project with many records and large amounts of data should use a smaller number. If a value greater
  than 365 is selected, it will default back to 31.
- `always-exclude-fields-with-regex` - providing a regular expression for this setting will result in any fields 
  matching the expression ALWAYS being removed from the list of log entries. For example, using a value such as
  '_monstat\$|_crfver\$' will exclude any fields whose names end with _monstat and _crfver
- `display-event-id-with-event-name` - when checked, the event names are shown with an event id suffix rather just name 
  i.e. 'Event 1 [23]' rather than simple 'Event 1'
- `display-arm-id-with-arm-name` - when checked, the arm names are shown with an arm id suffix rather just name
    i.e. 'Arm 2 [2]' rather than simple 'Arm 2'

#### Usage

- the module is only available to users who have the logging module permission (always available to superadmin)
- when the user is a member of a DAG, they can only see log entries for DAGS they have membership of	
- if the project does not use DAGs, the group column is hidden and there is no filtering option for groups
- if there are only single instances of Events (i.e. the default), then the event column is hidden as is the filter. If 
  the project has been set up for multiple events but there are no instances of multiple event logs, then the above will 
  still apply
- if there are only single Arms, then the arm column and filter are hidden
- users must either
	- select all records with a timeframe of no more than the number of days as given in `max-days-all-records`
	OR
	- select a single record and then can have unlimited timeframes
	
	- it is not possible to select every log entry in the project for the above reasons
-  the log includes an eyeball icon which is a link to the relevant form. Using the browser back button will return the
  user to the log with the same filters applied

#### Automation Testing

The module includes comprehensive **Cypress automated** tests using the **Cucumber/Gherkin framework**. To set up Cypress, refer to [Setup_Overview.md](https://github.com/CCTC-team/CCTC_REDCap_Docker/blob/redcap_val/Setup_Overview.md).

All automated test scripts are located in the `automated_tests` directory. The test suite automatically picks up the scripts from this folder. These scripts can also be used to manually test the external module. The directory contains:
- Fixture files
- User Requirement Specification (URS) documents
- Feature test scripts

**Step Definition Locations:**

Step definitions are organized across multiple locations in the `redcap_cypress` repo under `redcap_cypress/cypress/support/step_definitions/`:

- **Non-core feature step definitions** are in `redcap_cypress/cypress/support/step_definitions/noncore.js`
- **Shared EM step definitions** (used by more than one external module) are in `redcap_cypress/cypress/support/step_definitions/external_module.js`

#### GitHub Actions Workflow

The module ships with a CI workflow at [.github/workflows/cypress-tests.yml](.github/workflows/cypress-tests.yml) that runs this module's own Cypress specs end-to-end against a prebuilt all-in-one REDCap image, using a self-contained Cypress runner image. There is no 3-container compose, no host `npm ci`, and no cloning of the harness at runtime — both images are published ahead of time and pulled from GHCR.

**Triggers**
- `push` to `main` (ignoring doc-only changes: `**/*.md`, `LICENSE`, `.gitignore`, `docs/**`)
- Manual `workflow_dispatch`

**What it does** (`cypress-tests` job)
1. Checks out the Data Entry Log EM (this repo) into `data_entry_log_em/`.
2. Logs in to GHCR and pulls two prebuilt images: `redcap-aio` (REDCap + MariaDB + MailHog in one container via supervisord) and `cypress-runner-aio` (the suite with `rctf` + `redcap_rsvc` baked in).
3. Stages the EM under test — strips `.git`/`.github` so only the module payload remains.
4. Starts the AIO container (ports `8443`/`8025`, volume `cctc_mariadb_data`), bind-mounting **this commit's** EM over the image's `modules/data_entry_log_v1.1.1` so REDCap serves the code under test with no rebuild.
5. Waits for REDCap to come up (first boot initialises the DB).
6. Runs the runner image, which copies this module's `automated_tests` out of the container and runs only its `E.127.*` specs (excluding `*REDUNDANT*`), up to 3 attempts per spec, on Chromium. It reaches the DB/files over the mounted Docker socket and the UI over host networking.
7. Uploads the mochawesome reports (and, on failure, screenshots) as artifacts retained for 7 days.

**Follow-on jobs**
- `prune-artifacts` — deletes artifacts from older runs, keeping only the latest 2.
- `publish-report` — merges the run's mochawesome JSON into one combined HTML report and publishes it to GitHub Pages (report named `data_entry_log_v1.1.1.html`, also served at the Pages root as `index.html`).

**Required repository secrets**
- `CCTC_TEAM_PAT` — PAT with `read:packages` for the private `redcap-aio` / `cypress-runner-aio` GHCR images.

**Version pins** (set as `env` at the top of the workflow)
- `AIO_IMAGE` / `RUNNER_IMAGE` — the GHCR image refs; both must be built for the **same** REDCap version.
- `EM_NAME` / `EM_VERSION` — `data_entry_log` / `v1.1.1`. `EM_MODULE` (`data_entry_log_v1.1.1`) is the directory REDCap discovers the module by and the runner uses to locate the specs. Bump `EM_VERSION`/`EM_MODULE` when releasing a new module version so the mount path and spec discovery stay aligned.

#### Potential improvements

- amend stored procedure when filtering for values that have a text input and use a 'like' expression, and use a regex
  with 'rlike' in the stored procedure to make the filtering more flexible. This is an advanced option so users should
  still be able to use the basic filtering when required

---

## Who are we

The Cambridge Cancer Trials Centre (CCTC) is a collaboration between Cambridge University Hospitals NHS Foundation Trust, the University of Cambridge, and Cancer Research UK. Founded in 2007, CCTC designs and conducts clinical trials and studies to improve outcomes for patients with cancer or those at risk of developing it. In 2011, CCTC began hosting the Cambridge Clinical Trials Unit - Cancer Theme (CCTU-CT).

CCTC has two divisions: Cancer Theme, which coordinates trial delivery, and Clinical Operations.