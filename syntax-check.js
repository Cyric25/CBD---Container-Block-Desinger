/**
 * Syntax Checker for Container Block Designer Plugin
 * Checks PHP and JavaScript files for syntax errors
 */

const fs = require('fs');
const path = require('path');
const { exec } = require('child_process');
const { promisify } = require('util');
const glob = require('glob');
const chokidar = require('chokidar');

const execAsync = promisify(exec);

// Configuration
const config = {
    phpFiles: [
        'container-block-designer.php',
        'includes/**/*.php',
        'admin/**/*.php'
    ],
    jsFiles: [
        'assets/js/**/*.js',
        'admin/**/*.js'
    ],
    excludePatterns: [
        '**/node_modules/**',
        '**/vendor/**',
        '**/*.min.js',
        '**/dist/**',
        '**/interactivity-store.js' // WordPress Interactivity API module - skipped
    ]
};

// ANSI color codes
const colors = {
    reset: '\x1b[0m',
    bright: '\x1b[1m',
    red: '\x1b[31m',
    green: '\x1b[32m',
    yellow: '\x1b[33m',
    blue: '\x1b[34m',
    cyan: '\x1b[36m'
};

// Statistics
let stats = {
    phpFiles: 0,
    jsFiles: 0,
    phpErrors: 0,
    jsErrors: 0,
    startTime: null
};

/**
 * Print colored output
 */
function print(message, color = 'reset') {
    console.log(colors[color] + message + colors.reset);
}

/**
 * Get all files matching patterns
 */
function getFiles(patterns, excludePatterns) {
    const files = new Set();

    for (const pattern of patterns) {
        const matches = glob.sync(pattern, {
            cwd: __dirname,
            absolute: false,
            ignore: excludePatterns
        });

        matches.forEach(file => files.add(file));
    }

    return Array.from(files);
}

/**
 * Check PHP syntax
 */
async function checkPHPSyntax(filePath) {
    try {
        const fullPath = path.join(__dirname, filePath);

        // Check if php command is available
        try {
            await execAsync('php --version');
        } catch (error) {
            print(`⚠️  PHP command not found. Skipping PHP syntax checks.`, 'yellow');
            print(`   Install PHP CLI to enable PHP syntax checking.`, 'yellow');
            return { valid: true, skipped: true };
        }

        // Run php -l (lint)
        const { stdout, stderr } = await execAsync(`php -l "${fullPath}"`);

        if (stdout.includes('No syntax errors')) {
            return { valid: true, message: stdout.trim() };
        } else {
            return { valid: false, message: stderr || stdout };
        }
    } catch (error) {
        return {
            valid: false,
            message: error.stderr || error.message
        };
    }
}

/**
 * Check JavaScript syntax
 */
async function checkJSSyntax(filePath) {
    try {
        const fullPath = path.join(__dirname, filePath);
        const content = fs.readFileSync(fullPath, 'utf8');

        // Try to parse with Node.js VM
        const vm = require('vm');
        const context = vm.createContext({
            window: {},
            document: {},
            jQuery: function() {},
            $: function() {},
            console: console,
            wp: {},
            jsPDF: {},
            html2canvas: function() {}
        });

        try {
            new vm.Script(content, {
                filename: filePath,
                lineOffset: 0,
                columnOffset: 0
            });

            return { valid: true, message: 'No syntax errors detected' };
        } catch (parseError) {
            return {
                valid: false,
                message: `${parseError.name}: ${parseError.message} (line ${parseError.lineNumber || 'unknown'})`
            };
        }
    } catch (error) {
        return {
            valid: false,
            message: error.message
        };
    }
}

/**
 * Check a single file
 */
async function checkFile(filePath, type) {
    const result = type === 'php'
        ? await checkPHPSyntax(filePath)
        : await checkJSSyntax(filePath);

    if (result.skipped) {
        return result;
    }

    if (result.valid) {
        print(`  ✓ ${filePath}`, 'green');
        return result;
    } else {
        print(`  ✗ ${filePath}`, 'red');
        print(`    ${result.message}`, 'red');
        return result;
    }
}

/**
 * Run all syntax checks
 */
async function runChecks() {
    stats.startTime = Date.now();
    stats.phpErrors = 0;
    stats.jsErrors = 0;

    print('\n' + '='.repeat(60), 'cyan');
    print('  Container Block Designer - Syntax Check', 'bright');
    print('='.repeat(60) + '\n', 'cyan');

    // Check PHP files
    print('📋 Checking PHP files...', 'blue');
    const phpFiles = getFiles(config.phpFiles, config.excludePatterns);
    stats.phpFiles = phpFiles.length;

    let phpSkipped = false;

    for (const file of phpFiles) {
        const result = await checkFile(file, 'php');
        if (result.skipped) {
            phpSkipped = true;
            break;
        }
        if (!result.valid) {
            stats.phpErrors++;
        }
    }

    if (!phpSkipped) {
        print(`\n   Found ${phpFiles.length} PHP files, ${stats.phpErrors} errors\n`, stats.phpErrors > 0 ? 'yellow' : 'green');
    } else {
        print(`\n   PHP syntax checking skipped (PHP CLI not available)\n`, 'yellow');
    }

    // Check JavaScript files
    print('📋 Checking JavaScript files...', 'blue');
    const jsFiles = getFiles(config.jsFiles, config.excludePatterns);
    stats.jsFiles = jsFiles.length;

    for (const file of jsFiles) {
        const result = await checkFile(file, 'js');
        if (!result.valid) {
            stats.jsErrors++;
        }
    }

    print(`\n   Found ${jsFiles.length} JS files, ${stats.jsErrors} errors\n`, stats.jsErrors > 0 ? 'yellow' : 'green');

    // Summary
    const elapsed = ((Date.now() - stats.startTime) / 1000).toFixed(2);
    const totalErrors = stats.phpErrors + stats.jsErrors;

    print('='.repeat(60), 'cyan');
    print('  Summary', 'bright');
    print('='.repeat(60), 'cyan');
    print(`   Time: ${elapsed}s`);
    print(`   Total files: ${stats.phpFiles + stats.jsFiles}`);
    print(`   Total errors: ${totalErrors}`, totalErrors > 0 ? 'red' : 'green');

    if (totalErrors === 0) {
        print('\n✅ All checks passed!', 'green');
        print('', 'reset');
        return true;
    } else {
        print(`\n❌ ${totalErrors} error(s) found. Please fix them before building.`, 'red');
        print('', 'reset');
        return false;
    }
}

/**
 * Watch mode
 */
function watchMode() {
    print('\n👀 Watch mode enabled. Monitoring files for changes...', 'cyan');
    print('   Press Ctrl+C to exit\n', 'cyan');

    const watchPatterns = [...config.phpFiles, ...config.jsFiles];

    const watcher = chokidar.watch(watchPatterns, {
        ignored: config.excludePatterns,
        persistent: true,
        ignoreInitial: true,
        cwd: __dirname
    });

    watcher.on('change', async (filePath) => {
        print(`\n🔄 File changed: ${filePath}`, 'yellow');

        const ext = path.extname(filePath).toLowerCase();
        const type = ext === '.php' ? 'php' : 'js';

        await checkFile(filePath, type);
        print('');
    });

    watcher.on('error', (error) => {
        print(`❌ Watcher error: ${error}`, 'red');
    });

    // Initial check
    runChecks();
}

/**
 * Main execution
 */
async function main() {
    const args = process.argv.slice(2);
    const watchFlag = args.includes('--watch') || args.includes('-w');

    if (watchFlag) {
        watchMode();
    } else {
        const success = await runChecks();
        process.exit(success ? 0 : 1);
    }
}

// Run
main().catch(error => {
    print(`❌ Fatal error: ${error.message}`, 'red');
    process.exit(1);
});
