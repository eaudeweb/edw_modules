# edw_group

Enable the group module to provide functionality for private tabs for a meeting.

# Installation

1. Install the `edw_modules` suite using composer as instructed in the main module documentation
2. Enable the module using drush: `drush en edw_group`.

Before enabling this module, make sure that the following modules are present:
```php
"require": {
  "drupal/group": "^3.2",
  "drupal/node_access_grants": "^3.1",
}
```

Recommended patches for `group` module:

```php
"patches": {
    "drupal/entity_reference_revisions": {
      "#3364226 - Form error when no group admin role is automatically created": "https://www.drupal.org/files/issues/2023-08-02/group-form-error-3364226-12.patch",
      "#3104345 - Drupal\\group\\Plugin\\GroupContentEnablerBase::createAccess() must implement interface Drupal\\group\\Entity\\GroupInterface, null give": "https://git.drupalcode.org/project/group/-/merge_requests/86.patch",
      "#3397063 - Revisions tab appears twice on Groups": "https://www.drupal.org/files/issues/2023-12-12/group-revision-tabs-appear-twice-3397063-15.patch"
    }
}
```

Don't forget rebuilds the node access database: `node_access_rebuild(TRUE)`.

## Architecture

Group types:
- `event` - Meeting group type used to create groups for meetings.

### Fields

| Field label | Field name  | Description                                                                 | Field type                    | Cardinality | Required | Translatable | Widget       |
|-------------|-------------|-----------------------------------------------------------------------------|-------------------------------|-------------|----------|--------------|--------------|
| Title       | title       | Build-in                                                                    | Text                          | Single      | Yes      | Yes          | Text field   |
| Meeting     | field_event | Automatically populated when an user with permissions create a new meeting. | Node entity reference (Event) | Single      | Yes      | No           | Autocomplete |

## Functionalities

The following functionalities are provided out of the box:

1. Group roles for Administrator and general users. If your website use another
role as administrator (such as System) you need manually add new role by at:
`/admin/group/types/manage/event/roles`.
2. Two new fields: `field_groups`and `field_moderator_groups`:
   - `field_moderator_groups` - Users in these groups have the permission to
   edit the meeting/section and its documents;
   - `field_groups` - groups that can view the specific section;
   Go to `/admin/structure/types/manage/{entity_type}/form-display` and display
   these fields in the edit form.
3. Two permissions.
