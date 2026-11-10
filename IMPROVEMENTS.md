# Codebase Improvements Recommendations

## 🔒 Security Issues

### 1. **Path Traversal Vulnerability in `remove()` function** (Critical)
**Location:** `functions.php:205-222`

**Issue:** The function uses `urldecode()` on user input and concatenates it directly with the audio path without proper validation. This allows directory traversal attacks like `../../../etc/passwd`.

**Fix:**
```php
function remove($file) {
    $file = urldecode($file);
    $file = basename($file); // Prevent path traversal
    $audioPath = getConfig("audio_path");
    $filePath = realpath($audioPath . '/' . $file);
    $audioPathReal = realpath($audioPath);
    
    // Verify file is within audio path directory
    if ($filePath === false || strpos($filePath, $audioPathReal) !== 0) {
        return apiResponse("error", "Invalid file path.");
    }
    // ... rest of function
}
```

### 2. **Missing Path Traversal Protection in `getDuration()`**
**Location:** `functions.php:36-50`

**Issue:** Function accepts file paths without validation. Could be exploited if called with user-controlled input.

**Recommendation:** Add path validation or ensure it's only called with sanitized paths.

### 3. **XSS Vulnerability in HTML Output**
**Location:** Multiple locations (e.g., `functions.php:213, 216, 322`)

**Issue:** Error messages containing user input are wrapped in `<code>` tags but user input isn't escaped before being inserted.

**Fix:** Use `htmlspecialchars()` on all user-provided values before output:
```php
return apiResponse("error", "The directory <code>".htmlspecialchars(CONFIG["audio_path"], ENT_QUOTES, 'UTF-8')."</code> is not writable.");
```

### 4. **File Upload Security Enhancement**
**Location:** `functions.php:319`

**Issue:** While `basename()` is used, the filename could still contain dangerous characters. Also, MIME type validation is missing.

**Recommendation:**
- Validate MIME type against file extension
- Sanitize filename more aggressively
- Consider using a whitelist of allowed characters

### 5. **Missing Input Validation in API**
**Location:** `api.php:32-34, 37-39, 87-89`

**Issue:** User input from `$_GET` and `$_POST` is used without validation or sanitization.

**Recommendation:** Add validation functions for all inputs.

## 🐛 Bug Fixes

### 6. **Logic Error in `getDuration()`**
**Location:** `functions.php:41-43`

**Issue:** If file doesn't exist, the function sets `$duration` to an error message but continues execution, overwriting it on line 45.

**Fix:**
```php
if (!file_exists($file)) {
    return "File not found"; // or handle error appropriately
}
```

### 7. **Inconsistent Configuration Access**
**Location:** Multiple files

**Issue:** Code mixes `CONFIG["key"]["value"]` and `getConfig("key")` patterns.

**Recommendation:** Standardize on `getConfig("key")` for consistency and better error handling.

### 8. **Missing Null Check in `joinSession()` and `createSession()`**
**Location:** `api.php:82-89`

**Issue:** Functions are called without parameters but may need them. Missing validation.

### 9. **Unused Variable `$fileType`**
**Location:** `functions.php:325`

**Issue:** Variable is defined but never used.

### 10. **Potential Issue in `saveConfig()` - Array Handling**
**Location:** `functions.php:138`

**Issue:** Array values in config are concatenated without proper escaping for nested arrays or special characters.

## 💻 Code Quality Improvements

### 11. **Missing Type Declarations**
**Recommendation:** Add type hints to all function parameters and return types for better IDE support and error detection:
```php
function getDuration(string $file): string
function apiResponse(string $status, string $response, array $data = []): void
```

### 12. **Code Duplication**
**Location:** `functions.php:278-303`

**Issue:** The switch statement with `break;` after each `return` is redundant.

**Fix:** Remove unnecessary `break;` statements after `return`.

### 13. **Mixed Responsibilities**
**Location:** `functions.php:225-252`

**Issue:** `listSongs()` generates HTML strings, mixing business logic with presentation.

**Recommendation:** Return data arrays only, move HTML generation to view layer.

### 14. **Hardcoded Permissions**
**Location:** `functions.php:210, 379-380`

**Issue:** Using `0777` permissions is a security risk.

**Recommendation:** Use more restrictive permissions like `0755` or `0750`.

### 15. **Global State Usage**
**Location:** `functions.php:346, 391`

**Issue:** Functions use `global $_SESSION` instead of proper dependency injection.

**Recommendation:** Consider passing session data as parameters or using a session handler class.

### 16. **Inconsistent String Concatenation**
**Location:** Throughout codebase

**Issue:** Mix of string concatenation with `.` and interpolation.

**Recommendation:** Standardize on one approach (preferably interpolation for readability).

### 17. **Missing Error Handling**
**Location:** `functions.php:44`

**Issue:** `Mp3Info` constructor could throw exceptions, but they're not caught.

**Recommendation:**
```php
try {
    $mp3info = new wapmorgan\Mp3Info\Mp3Info($file);
    $duration = $mp3info->duration;
} catch (Exception $e) {
    return "0:00"; // or log error
}
```

## ⚡ Performance Improvements

### 18. **Inefficient File Operations**
**Location:** `functions.php:231-251`

**Issue:** `getDuration()` is called for every file in a loop, which involves file I/O and MP3 parsing.

**Recommendation:** 
- Cache duration values
- Process files in batches
- Consider storing metadata in a database

### 19. **Redundant Array Operations**
**Location:** `functions.php:231`

**Issue:** `array_diff(scandir(...), array('..', '.'))` creates intermediate arrays.

**Recommendation:** Use `array_filter` or directory iterator with filtering.

### 20. **No Caching**
**Recommendation:** Implement caching for:
- Configuration values
- File listings
- MP3 duration metadata

## 🏗️ Architecture Improvements

### 21. **Separation of Concerns**
**Recommendation:** 
- Create separate classes for:
  - Configuration management
  - File operations
  - Session management
  - HTML rendering

### 22. **API Response Wrapper**
**Location:** `api.php:94-100`

**Issue:** The response handling is confusing - calling `apiResponse()` twice.

**Recommendation:** Refactor to have a single, consistent response pattern.

### 23. **Error Handling Strategy**
**Recommendation:** 
- Implement proper exception handling
- Add logging mechanism
- Create error codes/enums instead of strings

### 24. **Configuration Management**
**Recommendation:**
- Use a configuration class instead of global arrays
- Implement validation for configuration values
- Add type checking for config values

### 25. **Dependency Injection**
**Recommendation:** Instead of global functions, use classes with dependency injection for better testability.

## 📝 Documentation

### 26. **Missing PHPDoc Comments**
**Recommendation:** Add PHPDoc comments to all functions:
```php
/**
 * Gets the duration of an MP3 file
 *
 * @param string $file Path to the MP3 file
 * @return string Duration in format "M:SS" or "0:00" on error
 * @throws Exception If file cannot be read
 */
```

### 27. **Code Comments**
**Recommendation:** Add inline comments explaining complex logic and business rules.

## 🔧 Additional Recommendations

### 28. **Add Unit Tests**
**Recommendation:** Create unit tests for critical functions, especially:
- File upload validation
- Path sanitization
- Configuration management

### 29. **Environment-Specific Configuration**
**Recommendation:** Ensure sensitive data (paths, permissions) are environment-specific and not hardcoded.

### 30. **Add Input Sanitization Helper**
**Recommendation:** Create helper functions:
```php
function sanitizePath(string $path): string
function sanitizeFilename(string $filename): string
function validateFileExtension(string $file, array $allowed): bool
```

### 31. **CSRF Protection**
**Recommendation:** Add CSRF tokens for state-changing operations (upload, delete, config changes).

### 32. **Rate Limiting**
**Recommendation:** Implement rate limiting for API endpoints to prevent abuse.

### 33. **Logging**
**Recommendation:** Add proper logging for:
- File operations
- Configuration changes
- Errors and exceptions
- Security events

### 34. **Constants Instead of Magic Strings**
**Location:** Throughout codebase

**Recommendation:** Define constants for:
- Action names
- Error messages
- File paths

### 35. **Remove Dead Code**
**Location:** `functions.php:190-202`

**Issue:** Commented out `download()` function.

**Recommendation:** Remove or implement.

---

## Priority Summary

**Critical (Fix Immediately):**
- #1 Path Traversal in `remove()`
- #3 XSS vulnerabilities
- #4 File upload security

**High Priority:**
- #6 Logic error in `getDuration()`
- #11 Type declarations
- #17 Exception handling
- #21 Separation of concerns

**Medium Priority:**
- #7 Configuration consistency
- #13 Mixed responsibilities
- #18 Performance optimizations
- #22 API response handling

**Low Priority:**
- #12 Code cleanup (redundant breaks)
- #26 Documentation
- #34 Constants
