
PHP library for sending data to [Insight Renderers](http://github.com/cadorn/insight/tree/master/meta/renderer/) via [Wildfire](http://github.com/cadorn/wildfire).



Dynamically Applying Patches
============================

When using an autoloader, code files may be automatically patched by insight to inject logging calls.
This is used when it is undesirable to have insight logging calls in the original code.

To register a directory containing patches use:

    Insight_Helper::plugin('patch')->addPatchDirectory(dirname(__DIR__).'/patches/');

Where the directory contains patches according to:

    .../patches/Namespace_Class_Name.patch

To patch files in the autoloader use the following to get a new file path:

    $file = \Insight_Helper::plugin('patch')->getClassFilePath($class, $file);

To generate a patch use:

    diff -cwb .../Modified/Namespace/Class/Name.php .../Original/Namespace/Class/Name.php > .../patches/Namespace_Class_Name.patch
