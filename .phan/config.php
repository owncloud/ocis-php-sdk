<?php
/**
 * @copyright Copyright (c) 2023, ownCloud GmbH
 */

use Phan\Issue;

return [

    // Supported values: '7.2', '7.3', '7.4', '8.0', null.
    // If this is set to null,
    // then Phan assumes the PHP version which is closest to the minor version
    // of the php executable used to execute phan.
    'target_php_version' => '8.1',

    // A list of directories that should be parsed for class and
    // method information. After excluding the directories
    // defined in exclude_analysis_directory_list, the remaining
    // files will be statically analyzed for errors.
    //
    // Thus, both first-party and third-party code being used by
    // your application should be included in this list.
    'directory_list' => [
        'src',
        'tests',
        'vendor'
    ],

    // A list of files to include in analysis
    'file_list' => [
    ],

    // A directory list that defines files that will be excluded
    // from static analysis, but whose class and method
    // information should be included.
    //
    // Generally, you'll want to include the directories for
    // third-party code (such as "vendor/") in this list.
    //
    // N.b.: If you'd like to parse but not analyze 3rd
    //       party code, directories containing that code
    //       should be added to both the `directory_list`
    //       and `exclude_analysis_directory_list` arrays.
    'exclude_analysis_directory_list' => [
        'vendor',
        'tests/integration/ocis'
    ],

    // If true, missing properties will be created when
    // they are first seen. If false, we'll report an
    // error message.
    "allow_missing_properties" => false,

    // If enabled, allow null to be cast as any array-like type.
    // This is an incremental step in migrating away from null_casts_as_any_type.
    // If null_casts_as_any_type is true, this has no effect.
    "null_casts_as_any_type" => false,

    // Backwards Compatibility Checking. This is slow
    // and expensive, but you should consider running
    // it before upgrading your version of PHP to a
    // new version that has backward compatibility
    // breaks.
    'backward_compatibility_checks' => false,

    // The initial scan of the function's code block has no
    // type information for `$arg`. It isn't until we see
    // the call and rescan test()'s code block that we can
    // detect that it is actually returning the passed in
    // `string` instead of an `int` as declared.
    'quick_mode' => false,

    // The minimum severity level to report on. This can be
    // set to Issue::SEVERITY_LOW, Issue::SEVERITY_NORMAL or
    // Issue::SEVERITY_CRITICAL. Setting it to only
    // critical issues is a good place to start on a big
    // sloppy mature code base.
    'minimum_severity' => Issue::SEVERITY_LOW,

    // A set of fully qualified class-names for which
    // a call to parent::__construct() is required
    'parent_constructor_required' => [
    ],

];
