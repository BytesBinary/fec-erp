# Roles and permissions

Authorization is centralised in one class (`App\Support\Authorization\Authorizer`). Every web screen, MCP tool and assistant action asks it the same question: *may this user do this permission on this record?* Roles are stored with spatie/laravel-permission; Filament Shield provides the **Roles & permissions** screen, so roles and permissions can be created and edited in the UI (super admin).

## Roles

| Role | Scope | What it is for |
|---|---|---|
| **Super admin** (`super_admin`) | global | Everything, everywhere (global bypass). The only role that manages roles, the permission matrix, security policy, email rules and templates, grading scale and clearance stages. |
| **Administration office** (`admin_office`) | global | Day-to-day office: people, enrollment, clearance desk (print, hand-over), result sheets, portal monitor, email delivery history. |
| **Head of institution** (`head_of_institution`) | global | Last stage of the clearance chain; read access to academic data. |
| **Principal** (`principal`) | global | Institution-wide read access to people, batches and results. |
| **Department head** (`department_head`) | department (pinned to department ids) | Own department: courses, students, result approval, department stage of clearance, portal pulls of the department. |
| **Hall provost** (`hall_provost`) | hall (pinned to hall ids) | Own hall: residents, dues, hall stage of clearance, hall notices. |
| **Librarian** (`librarian`) | global | Library loans and fines, library stage of clearance. |
| **Teacher** (`teacher`) | course (own courses) | Own courses: roster, marks entry, submit for approval; own routine. |
| **Student** (`student`) | self | Only own data: profile, results, clearance, devices, 2FA, AI integrations, assistant. |

The four legacy placeholder roles (Academic Admin, Exam Coordinator, Routine Coordinator, Report Viewer) from the original routine and exam modules are kept; they are global (no scope).

## Data scope

A permission is granted through a role; the role's **scope** limits which records it applies to. `department_head` is pinned to one or more departments, `hall_provost` to halls, `teacher` to the courses they teach, `student` to their own record. Roles created in the UI are global unless they are listed under `rbac.role_scopes` in `config/erp.php` (decision D-024). Read permissions listed under `unscoped_permissions` (catalog data such as the course list) are open to every holder.

## Permission catalog

77 native permissions are listed below. Spec names such as `course:update` are aliases of Filament Shield permissions (`Update:Course`); Shield also generates one permission per resource, page and widget so the Roles screen can show or hide any menu item.

| Permission | Meaning |
|---|---|
| `audit_log:view` | View the audit log |
| `clearance:apply` | Apply for clearance |
| `clearance:approve` | Approve a clearance stage |
| `clearance:cancel` | Cancel clearance requests |
| `clearance:mark_collected` | Mark clearance as collected |
| `clearance:print` | Print clearance certificates |
| `clearance:reject` | Reject a clearance stage |
| `clearance:search` | Search clearance requests |
| `clearance:view` | View clearance requests |
| `clearance_stage:manage` | Configure clearance stages |
| `course_offering:create` | Create course offerings |
| `course_offering:list` | List course offerings |
| `course_offering:update` | Update course offerings |
| `email_delivery:retry` | Retry failed emails |
| `email_delivery:view` | See the email delivery history |
| `email_template:manage` | Create and edit email templates |
| `enrollment:bulk_create` | Bulk-enroll students |
| `enrollment:create` | Enroll students |
| `enrollment:drop` | Drop enrollments |
| `enrollment:list` | List enrollments |
| `grading_scale:manage` | Edit the grading scale |
| `hall:assign_student` | Assign students to halls |
| `hall:create` | Create halls |
| `hall:list` | List halls |
| `hall:update` | Update halls |
| `hall:view` | View a hall |
| `hall_dues:manage` | Record and settle hall dues |
| `hall_dues:view` | View hall dues |
| `library_dues:view` | View library fines |
| `library_loans:manage` | Issue and return library loans |
| `library_loans:view` | View library loans |
| `mcp:configure` | Switch MCP on or off globally and per role |
| `mcp_integration:manage_all` | View and revoke every user's AI integrations |
| `notice:create` | Create notices |
| `notice:delete` | Delete notices |
| `notice:list` | List notices |
| `notice:update` | Update notices |
| `notice:view` | View a notice |
| `notification_rule:manage` | Enable, disable and configure email events, send test emails |
| `notification_rule:view` | See the email event rules and previews |
| `permission_matrix:update` | Change the permission matrix |
| `permission_matrix:view` | View the permission matrix |
| `portal_monitor:manage` | Sync the exam list, run the daily check and start publication pulls |
| `portal_monitor:view` | See the result portal monitor (exam list, daily check, publications) |
| `profile:update` | Edit student profiles (including locked fields) |
| `profile:update_own` | Complete and update own student profile |
| `profile:view` | View student profiles |
| `profile_field:manage` | Configure the required profile fields |
| `program:create` | Create programs |
| `program:list` | List programs |
| `program:update` | Update programs |
| `program:view` | View a program |
| `result:approve` | Approve results |
| `result:enter_marks` | Enter marks |
| `result:publish` | Publish results |
| `result:submit` | Submit results for approval |
| `result:view` | View results |
| `result_pull:list` | See which students' official results were pulled (success / failed) |
| `result_pull:retry` | Retry failed result pulls |
| `role:assign` | Assign roles to users |
| `role:list` | List roles |
| `role:revoke` | Revoke roles from users |
| `security:manage` | Manage the security policy (2FA per role) |
| `semester:activate` | Set the active semester |
| `semester:create` | Create semesters |
| `semester:list` | List semesters |
| `semester:update` | Update semesters |
| `semester:view` | View a semester |
| `session:revoke` | Revoke any user's login sessions |
| `session:view_any` | View any user's login sessions |
| `transcript:view` | View transcripts |
| `two_factor:reset` | Reset a user's two-factor authentication |
| `user:create` | Create users |
| `user:deactivate` | Deactivate / reactivate users |
| `user:list` | List users |
| `user:update` | Update users |
| `user:view` | View a user |

## Default role to permission matrix

Seeded defaults (editable at runtime on the Roles screen or with the `permission_matrix_update` MCP tool; an admin's edits are never overwritten by re-seeding). Super admin is not listed: it has every permission.

### Administration office (`admin_office`) — 41 permissions

`result_pull:list`, `result_pull:retry`, `portal_monitor:view`, `portal_monitor:manage`, `notification_rule:view`, `email_delivery:view`, `email_delivery:retry`, `department:list`, `department:view`, `program:list`, `program:view`, `semester:list`, `semester:view`, `course:list`, `course:view`, `batch:list`, `batch:view`, `student:list`, `student:view`, `teacher:list`, `teacher:view`, `user:list`, `user:view`, `hall:list`, `hall:view`, `result:view`, `transcript:view`, `enrollment:list`, `clearance:view`, `clearance:search`, `clearance:print`, `clearance:mark_collected`, `enrollment:create`, `enrollment:bulk_create`, `enrollment:drop`, `profile:view`, `profile:update`, `course_offering:list`, `course_offering:create`, `notice:list`, `notice:view`

### Head of institution (`head_of_institution`) — 23 permissions

`department:list`, `department:view`, `program:list`, `program:view`, `semester:list`, `semester:view`, `course:list`, `course:view`, `student:list`, `student:view`, `teacher:list`, `teacher:view`, `hall:list`, `hall:view`, `result:view`, `transcript:view`, `clearance:view`, `clearance:approve`, `clearance:reject`, `notice:list`, `notice:view`, `notice:create`, `notice:update`

### Principal (`principal`) — 16 permissions

`department:list`, `department:view`, `program:list`, `program:view`, `semester:list`, `semester:view`, `course:list`, `course:view`, `student:list`, `student:view`, `teacher:list`, `teacher:view`, `result:view`, `clearance:view`, `notice:list`, `notice:view`

### Department head (`department_head`) — 30 permissions

`result_pull:list`, `result_pull:retry`, `department:list`, `department:view`, `program:list`, `program:view`, `semester:list`, `semester:view`, `course:list`, `course:view`, `course:create`, `course:update`, `course:assign_teacher`, `student:list`, `student:view`, `teacher:list`, `teacher:view`, `enrollment:list`, `result:view`, `result:approve`, `course_offering:list`, `course_offering:create`, `profile:view`, `clearance:view`, `clearance:approve`, `clearance:reject`, `notice:list`, `notice:view`, `notice:create`, `notice:update`

### Hall provost (`hall_provost`) — 14 permissions

`hall:list`, `hall:view`, `hall:assign_student`, `hall_dues:view`, `hall_dues:manage`, `student:list`, `student:view`, `clearance:view`, `clearance:approve`, `clearance:reject`, `notice:list`, `notice:view`, `notice:create`, `notice:update`

### Librarian (`librarian`) — 10 permissions

`student:list`, `student:view`, `library_loans:view`, `library_loans:manage`, `library_dues:view`, `clearance:view`, `clearance:approve`, `clearance:reject`, `notice:list`, `notice:view`

### Teacher (`teacher`) — 15 permissions

`department:list`, `department:view`, `program:list`, `program:view`, `semester:list`, `semester:view`, `course:list`, `course:view`, `enrollment:list`, `result:view`, `result:enter_marks`, `result:submit`, `course_offering:list`, `notice:list`, `notice:view`

### Student (`student`) — 16 permissions

`program:list`, `program:view`, `semester:list`, `semester:view`, `course:list`, `course:view`, `student:view`, `enrollment:list`, `result:view`, `transcript:view`, `profile:update_own`, `clearance:apply`, `clearance:view`, `clearance:cancel`, `notice:list`, `notice:view`

