# moodle-tool_encoded

## What is this?

At some time in the past there was a core bug, probably limited to the Atto editor. This mean that
when content such as an image or video was inserted into an editor instead of being correctly
saved using the Moodle File API it was turned into inline base64 content in the html. In some cases
like video's this massively bloats the html and causes all sorts of side effects such as high memory
consumption and slower performance.

See this tracker for more details:

https://tracker.moodle.org/browse/MDL-80104

This is an admin tool for Moodle which provides a way to generate reports for columns that may
contain base64 encoded data and then automated the fixing of this data back into normal plugin files.

* [Branches](#branches)
* [Installation](#installation)
* [Usage](#usage)
* [GDPR](#gdpr)
* [Contributing and support](#contributing-and-support)

## Branches

| Moodle version | Branch              | PHP  |
|----------------|---------------------|------|
| Moodle 4.1+    | `MOODLE_401_STABLE` | 7.4+ |

## Installation
1. Clone this repository into admin/tool/encoded
2. Install the plugins through the moodle GUI
3. Configure the plugin
    1. Configure the size of the files to flag in the report default is 10kb

## Usage
1. Navigate to 'Site admin > Plugins > Admin tools > Base64 Encoder > Generate report'
2. Select a table to generate the report
   1. An option exists to generate reports for every identified table
3. The task will be queued and the report will be generated
   1. A notice will be displayed to inform you that a report will be generated
   2. No feedback is given currently on the status of the task
4. Navigate to 'Site admin > Plugins > Admin tools > Base64 Encoder > Display report'
   1. You can also navigate to this page from a link in the report generation page
5. Any found records will be displayed in a report builder table that can be filtered and sorted
6. From this table you can choose to run additional actions (where supported):
   1. View the problematic record in a text editor
   2. Queue an attempt to automatically migrate the base64 data to a pluginfile

## Supported Tables
This tool will detect base64 data in all tables, but at this time view and migrate support has only been implemented for a few specific tables/columns. To add a new table:
1. Add mapping to the get_all_mapping function in the helper class. The mapping should include the table name, column name, and the file info that corresponds to where a file uploaded in the GUI would be stored in mdl_files. You can get these values by saving a file in the GUI.
   * The view link in the mapping should lead to the page where the text field can be edited in the GUI. This is highly recommended as saving over an item in the GUI will usually convert the base64 data to a pluginfile without requiring a migration (aside from questions where versioning is involved).
2. Migrations also require the instance id, which is the id of the context i.e. CONTEXT_COURSE uses course id and CONTEXT_MODULE uses coursemodule id. If the module instance can be found in the same table you can specify a 'simplelookup' with the field name in the mapping, otherwise specific handling will need to be added to the helper functions in get_instance_id().

## GDPR
This plugin is GDPR-compliant as it only stores the reference to records and does not restore user data.

Contributing and support
-----------------------
Issues, and pull requests using github are welcome and encouraged!

https://github.com/catalyst/moodle-tool_encoded/issues
