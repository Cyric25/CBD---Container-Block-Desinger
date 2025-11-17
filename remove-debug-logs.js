/**
 * Remove Debug Logs from Container Block Designer Plugin
 * Removes all CBD debug console.log statements
 */

const fs = require('fs');
const path = require('path');
const glob = require('glob');

// Configuration
const config = {
    jsFiles: [
        'assets/js/**/*.js',
        'admin/**/*.js'
    ],
    excludePatterns: [
        '**/node_modules/**',
        '**/vendor/**',
        '**/*.min.js',
        '**/dist/**',
        '**/syntax-check.js',
        '**/create-plugin-zip.js',
        '**/remove-debug-logs.js'
    ],
    // Patterns to remove (regex patterns)
    removePatterns: [
        /console\.log\(['"]\[?CBD[^\)]*\);?\s*/g,
        /console\.log\('CBD[^)]*\);?\s*/g,
        /console\.log\("CBD[^)]*\);?\s*/g,
        /console\.log\(`CBD[^)]*\);?\s*/g,
        /console\.error\(['"]\[?CBD[^\)]*\);?\s*/g,
        /console\.warn\(['"]\[?CBD[^\)]*\);?\s*/g,
        /console\.info\(['"]\[?CBD[^\)]*\);?\s*/g,
        /console\.debug\(['"]\[?CBD[^\)]*\);?\s*/g
    ],
    // Keep these patterns (important logs)
    keepPatterns: [
        'Fatal error',
        'CRITICAL',
        'Error:',
        'Failed to'
    ]
};

// ANSI color codes
const colors = {
    reset: '\x1b[0m',
    red: '\x1b[31m',
    green: '\x1b[32m',
    yellow: '\x1b[33m',
    cyan: '\x1b[36m'
};

function print(message, color = 'reset') {
    console.log(colors[color] + message + colors.reset);
}

/**
 * Get all JavaScript files
 */
function getJSFiles() {
    const files = new Set();

    for (const pattern of config.jsFiles) {
        const matches = glob.sync(pattern, {
            cwd: __dirname,
            absolute: false,
            ignore: config.excludePatterns
        });

        matches.forEach(file => files.add(file));
    }

    return Array.from(files);
}

/**
 * Check if line should be kept
 */
function shouldKeepLine(line) {
    for (const pattern of config.keepPatterns) {
        if (line.includes(pattern)) {
            return true;
        }
    }
    return false;
}

/**
 * Remove debug logs from content
 */
function removeDebugLogs(content, filePath) {
    let modified = content;
    let removedCount = 0;
    const removedLines = [];

    // Split into lines for detailed tracking
    const lines = content.split('\n');
    const newLines = [];

    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];
        let keep = true;

        // Check if line contains a CBD debug log
        for (const pattern of config.removePatterns) {
            if (pattern.test(line) && !shouldKeepLine(line)) {
                keep = false;
                removedCount++;
                removedLines.push({
                    line: i + 1,
                    content: line.trim()
                });
                break;
            }
        }

        if (keep) {
            newLines.push(line);
        }
    }

    modified = newLines.join('\n');

    return {
        modified,
        removedCount,
        removedLines
    };
}

/**
 * Process a single file
 */
function processFile(filePath) {
    try {
        const fullPath = path.join(__dirname, filePath);
        const content = fs.readFileSync(fullPath, 'utf8');

        const result = removeDebugLogs(content, filePath);

        if (result.removedCount > 0) {
            // Create backup
            const backupPath = fullPath + '.backup';
            fs.writeFileSync(backupPath, content, 'utf8');

            // Write modified content
            fs.writeFileSync(fullPath, result.modified, 'utf8');

            print(`\n  ✓ ${filePath}`, 'green');
            print(`    Removed ${result.removedCount} debug log(s)`, 'yellow');

            // Show first 3 removed lines as examples
            const examples = result.removedLines.slice(0, 3);
            examples.forEach(item => {
                const preview = item.content.length > 60
                    ? item.content.substring(0, 60) + '...'
                    : item.content;
                print(`    Line ${item.line}: ${preview}`, 'cyan');
            });

            if (result.removedLines.length > 3) {
                print(`    ... and ${result.removedLines.length - 3} more`, 'cyan');
            }

            return result.removedCount;
        } else {
            print(`  - ${filePath} (no debug logs)`, 'reset');
            return 0;
        }
    } catch (error) {
        print(`  ✗ ${filePath}`, 'red');
        print(`    Error: ${error.message}`, 'red');
        return 0;
    }
}

/**
 * Main execution
 */
function main() {
    print('\n' + '='.repeat(60), 'cyan');
    print('  Container Block Designer - Remove Debug Logs', 'cyan');
    print('='.repeat(60) + '\n', 'cyan');

    const jsFiles = getJSFiles();
    print(`📋 Found ${jsFiles.length} JavaScript files to process\n`, 'cyan');

    let totalRemoved = 0;
    let filesModified = 0;

    for (const file of jsFiles) {
        const removed = processFile(file);
        if (removed > 0) {
            filesModified++;
            totalRemoved += removed;
        }
    }

    print('\n' + '='.repeat(60), 'cyan');
    print('  Summary', 'cyan');
    print('='.repeat(60), 'cyan');
    print(`   Files processed: ${jsFiles.length}`);
    print(`   Files modified: ${filesModified}`);
    print(`   Debug logs removed: ${totalRemoved}`);

    if (filesModified > 0) {
        print(`\n✅ Successfully removed ${totalRemoved} debug log(s) from ${filesModified} file(s)`, 'green');
        print(`\n💾 Backup files created with .backup extension`, 'yellow');
        print(`   You can restore files if needed by copying .backup files back`, 'yellow');
    } else {
        print('\n✅ No debug logs found!', 'green');
    }

    print('', 'reset');
}

// Run
try {
    main();
} catch (error) {
    print(`\n❌ Fatal error: ${error.message}`, 'red');
    process.exit(1);
}
