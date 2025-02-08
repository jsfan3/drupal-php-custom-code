# Drupal PHP Custom Code (Experimental)

**PHP Custom Code** is an experimental Drupal 10 module that provides an administration interface for adding custom PHP code blocks. These code blocks can be executed globally on every page or only on specified pages. This module is designed for advanced site administrators who understand the risks associated with executing dynamic PHP code. It is intended to be used in conjunction with or as an alternative to the PHP text format (such as provided by the [PHP module](https://www.drupal.org/project/php)).

> **WARNING:** Executing arbitrary PHP code (especially via `eval()`) is extremely dangerous. A single syntax or runtime error in a global code block may crash the entire site. For safety, an emergency disable URL is provided to quickly disable all code blocks if needed.

## Overview

This module offers:
- **Admin UI:** Under *Configuration → System → PHP Custom Code*, administrators can add, edit, enable/disable, and remove PHP code blocks.
- **Global vs. Page-Specific Execution:** Mark a code block as “global” to execute it on every page, or uncheck “Global” and specify a comma-separated list of Drupal paths where the block should execute.
- **Emergency Disable:** A special URL (`/admin/config/system/php-custom-code/emergency-disable`) is provided to disable all PHP code blocks, which is very useful in case a code error causes the site to crash.
- **Custom Storage:** All code blocks are stored in a dedicated database table (`php_custom_code`) created using Drupal’s `hook_schema()`.

## When to Use This Module

Use this module only when:
- You need to centralize custom PHP logic that you want to make available across various pages.
- You are working in an environment where you trust the administrators to manage the PHP code safely.
- You want to experiment with the integration of dynamic PHP code (for example, declaring functions or classes) that can later be called from content using the PHP text format.
- You want an emergency mechanism to disable all custom PHP code in case something goes wrong.

**Important:** This module is in an experimental phase. I plan to eventually submit it to drupal.org after thorough testing and verification that it does not cause issues. Use it only on trusted environments and with extreme caution.

## How It Works

1. **Execution Order:**  
   The module uses an event subscriber that listens to the `KernelEvents::REQUEST` event. This means that all enabled PHP code blocks are executed early in the page request process – *before* the main content is rendered.  
   - **Order of Execution:** The code blocks are executed in ascending order based on their `id` (i.e., in the order they were inserted). This allows you to manage dependencies if one block defines functions or classes that another block or subsequent PHP code (e.g., within an article using the PHP text format) relies upon.

2. **Integration with PHP Text Format:**  
   If a PHP code block is used solely for declaring classes or functions, then when it is evaluated (via `eval()` during the request event), those classes and functions become available globally. This means that later in the same request, PHP code embedded in articles using the PHP text format (i.e., `<?php ... ?>`) will have access to these declarations.  
   - **Caution with `eval()` and `try/catch`:** While `try/catch` is used to catch runtime exceptions during the evaluation, it cannot intercept syntax errors. Ensure that any code inserted is syntactically valid to prevent fatal errors.

## Installation

### Adding the Repository in Composer

To install this module via Composer from GitHub, you must add the repository as a VCS repository in your project's `composer.json`. Since the repository URL is `https://github.com/jsfan3/drupal-php-custom-code` and the package name is `"jsfan3/php-custom-code"`, add the following section to your `composer.json`:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/jsfan3/drupal-php-custom-code"
    }
  ]
}
```

Then, require the package using:

```bash
composer require jsfan3/php-custom-code:dev-main
```

### Enabling the Module

Once the module is downloaded, enable it using Drush or the Drupal Extend UI:

```bash
drush en php_custom_code -y
```

### Configuring the Module

Navigate to **Configuration → System → PHP Custom Code** to add your PHP code blocks.

## Usage Examples

### Example 1: Declaring a Utility Function Globally
- **Scenario:** You want to define a helper function that can be used in multiple content items.
- **Steps:**
  1. Create a new code block.
  2. Enter a title like "Global Utility Function".
  3. Paste the following code (without the `<?php` tags):
     ```php
     if (!function_exists('my_helper_function')) {
       function my_helper_function($value) {
         return strtoupper($value);
       }
     }
     ```
  4. Check **Enabled** and **Global (execute on all pages)**.
  5. Save the configuration.
- **Result:** On every page request, this block is executed during the request event. Therefore, when a PHP text format content (e.g., an article) uses:
  ```php
  <?php
    echo my_helper_function("hello world");
  ?>
  ```
  it will output `HELLO WORLD`.

### Example 2: Page-Specific Code for Custom Layouts
- **Scenario:** You want to run some PHP code only on your blog pages to set a special variable.
- **Steps:**
  1. Create a new code block.
  2. Enter a title like "Blog Layout Setup".
  3. Paste your PHP code, for example:
     ```php
     $GLOBALS['blog_layout'] = 'custom_layout';
     ```
  4. Uncheck **Global** and in the **Pages (comma-separated)** field, add the path (e.g., `/blog`).
  5. Enable and save the block.
- **Result:** The custom PHP code runs only on pages whose path contains `/blog`. Later, if your article (using the PHP text format) contains:
  ```php
  <?php
    if (isset($GLOBALS['blog_layout'])) {
      echo "Using " . $GLOBALS['blog_layout'];
    }
  ?>
  ```
  it will output the expected layout string.

## Emergency Disable

If a problematic code block causes the site to crash, you can quickly disable all PHP code blocks by navigating to:
```
/admin/config/system/php-custom-code/emergency-disable
```
This action sets the `enabled` flag to `0` for all blocks, halting their execution until you resolve the issue.

## Caveats and Warnings

- **Risky `eval()`:** This module uses `eval()` to execute code, which is inherently risky. Always test your code in a development environment before deploying it to production.
- **Syntax Errors:** The try/catch block can catch runtime exceptions but will not catch syntax errors. A syntax error in any block may lead to a fatal error.
- **Execution Order:** Blocks are executed in the order of their creation (by `id`). Be mindful of dependencies — if multiple blocks declare functions or classes with the same name, you might encounter redeclaration errors.
- **Experimental Module:** This module is experimental. I intend to further refine and eventually submit it to drupal.org after confirming its stability and security.

## Conclusion

**PHP Custom Code** provides a flexible way to centralize custom PHP logic outside of node content, which can be particularly useful when used together with the PHP text format. However, due to the risks associated with executing dynamic code, this module should only be used by trusted administrators and within a controlled environment.

Please use with caution and feel free to contribute feedback or improvements.

