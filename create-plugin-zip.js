/**
 * ZIP Creator for Container Block Designer Plugin
 * Creates a WordPress-ready plugin ZIP file with auto-incremented version number
 * Keeps maximum 4 ZIP files (3 old backups + current version)
 */

const fs = require('fs');
const path = require('path');
const archiver = require('archiver');
const { execFileSync } = require('child_process');

// PHP 7.4-Kompatibilitätscheck (lokal läuft oft PHP 8.x, das 8.0-only-Syntax
// nicht als Fehler meldet). Bricht den ZIP-Bau ab, wenn 8.0-Syntax gefunden wird.
function checkPhp74Compatibility() {
    const checker = path.join(__dirname, 'tools', 'check-php74.php');
    if (!fs.existsSync(checker)) {
        console.warn('⚠️  7.4-Check übersprungen: tools/check-php74.php nicht gefunden.');
        return;
    }
    try {
        const out = execFileSync('php', [checker, __dirname], { encoding: 'utf8' });
        process.stdout.write(out);
    } catch (err) {
        // exit 2 = Parser nicht verfügbar (nur Warnung); exit 1 = echte Fehler (Abbruch)
        if (err.status === 2) {
            console.warn('⚠️  ' + (err.stderr || '7.4-Check übersprungen (Parser nicht verfügbar).').trim());
            return;
        }
        if (err.code === 'ENOENT') {
            console.warn('⚠️  7.4-Check übersprungen: PHP-CLI nicht im PATH.');
            return;
        }
        if (err.stdout) process.stdout.write(err.stdout);
        if (err.stderr) process.stderr.write(err.stderr);
        console.error('\n❌ ZIP-Bau abgebrochen: Code ist nicht PHP-7.4-kompatibel (siehe oben).');
        process.exit(1);
    }
}

// Read version from main plugin file
function getPluginVersion() {
    const mainFile = path.join(__dirname, 'container-block-designer.php');
    const content = fs.readFileSync(mainFile, 'utf8');
    const versionMatch = content.match(/define\('CBD_VERSION',\s*'([^']+)'\)/);
    if (versionMatch && versionMatch[1]) {
        return versionMatch[1];
    }
    throw new Error('Could not find CBD_VERSION in container-block-designer.php');
}

// Increment patch version (e.g., 2.9.3 -> 2.9.4)
function incrementVersion(version) {
    const parts = version.split('.');
    if (parts.length !== 3) {
        throw new Error(`Invalid version format: ${version}`);
    }
    const major = parseInt(parts[0]);
    const minor = parseInt(parts[1]);
    const patch = parseInt(parts[2]);
    return `${major}.${minor}.${patch + 1}`;
}

// Update version in main plugin file
function updatePluginVersion(newVersion) {
    const mainFile = path.join(__dirname, 'container-block-designer.php');
    let content = fs.readFileSync(mainFile, 'utf8');

    // Update Version in plugin header
    content = content.replace(
        /(\* Version:\s*)[\d.]+/,
        `$1${newVersion}`
    );

    // Update CBD_VERSION constant
    content = content.replace(
        /define\('CBD_VERSION',\s*'[\d.]+'\)/,
        `define('CBD_VERSION', '${newVersion}')`
    );

    fs.writeFileSync(mainFile, content, 'utf8');
    console.log(`✓ Updated version to ${newVersion} in container-block-designer.php`);
}

// PHP 7.4-Kompatibilität prüfen BEVOR Version erhöht/ZIP gebaut wird
checkPhp74Compatibility();

// Configuration
const pluginName = 'container-block-designer';
const currentVersion = getPluginVersion();
const newVersion = incrementVersion(currentVersion);
const outputDir = path.join(__dirname, 'dist');
const outputPath = path.join(outputDir, `${pluginName}-${newVersion}.zip`);

// Update version in plugin file
updatePluginVersion(newVersion);

// Files and directories to include
const includePaths = [
    'admin',
    'assets',
    'includes',
    'vendor',  // IMPORTANT: Composer dependencies (mPDF + TCPDF fallback)
    'languages',
    'container-block-designer.php',
    'LICENSE'
    // Hinweis: Status-/Entwickler-MD-Dateien (FRONTEND_STATUS.md etc.) und
    // debug-pdf.php werden bewusst NICHT mehr ausgeliefert.
    // README.md existiert derzeit nicht und ist daher nicht gelistet.
];

// Files and directories to exclude
const excludePatterns = [
    'node_modules',
    '.git',
    '.gitignore',
    'dist',
    'create-plugin-zip.js',
    'package.json',
    'package-lock.json',
    'composer.json',
    'composer.lock',
    'composer.phar',
    '.DS_Store',
    'Thumbs.db',
    '.vscode',
    '.idea',
    '.claude',
    '*.log',
    '*.tmp',
    'syntax-check.js',
    'Ordnerstruktur.txt',
    'example-import.md',
    // Vendor: exclude dev-only packages (not needed in production)
    // IMPORTANT: Only exclude confirmed DEV packages from composer.lock
    'phpunit',
    'php-code-coverage',
    'php-file-iterator',
    'php-invoker',
    'php-text-template',
    'php-timer',
    'phpcodesniffer',
    'php_codesniffer',
    'squizlabs',
    'wpcs',
    'wp-coding-standards',
    'phar-io',
    'theseer',
    'nikic',
    'sebastian',
    'doctrine',
    'dealerdirect',
    'ttfonts_backup',
];

// Create output directory if it doesn't exist
if (!fs.existsSync(outputDir)) {
    fs.mkdirSync(outputDir, { recursive: true });
    console.log(`✓ Created output directory: ${outputDir}`);
}

// Manage ZIP file versions (keep max 4: 3 old backups + current)
const zipFiles = fs.readdirSync(outputDir)
    .filter(file => file.startsWith(`${pluginName}-`) && file.endsWith('.zip'))
    .map(file => ({
        name: file,
        path: path.join(outputDir, file),
        mtime: fs.statSync(path.join(outputDir, file)).mtime
    }))
    .sort((a, b) => a.mtime - b.mtime); // Sort oldest first

// If we already have 4 or more ZIPs, delete the oldest ones until only 3 remain
while (zipFiles.length >= 4) {
    const oldestZip = zipFiles.shift();
    fs.unlinkSync(oldestZip.path);
    console.log(`✓ Removed old backup: ${oldestZip.name}`);
}

// If the new version already exists, remove it (e.g., re-running script)
if (fs.existsSync(outputPath)) {
    fs.unlinkSync(outputPath);
    console.log(`✓ Removed existing ZIP for version ${newVersion}`);
}

// KRITISCH: Autoloader ohne Dev-Pakete generieren, BEVOR gezippt wird.
// Das ZIP schließt Dev-Pakete (phpunit, wpcs, ...) aus — ein mit Dev-Paketen
// generierter Autoloader lädt aber z. B. phpunit/.../Functions.php fest ein
// → Fatal Error / HTTP 500 auf der Zielinstallation (passiert bei v3.1.63-65).
// Nach dem ZIP-Bau wird der Dev-Autoloader wiederhergestellt (siehe unten).
const { execSync } = require('child_process');
console.log(`\n🔧 Generiere Produktions-Autoloader (composer dump-autoload --no-dev)...`);
execSync('composer dump-autoload --no-dev --optimize --quiet', { cwd: __dirname, stdio: 'inherit' });

function restoreDevAutoloader() {
    try {
        execSync('composer dump-autoload --optimize --quiet', { cwd: __dirname, stdio: 'inherit' });
        console.log('🔧 Dev-Autoloader wiederhergestellt.');
    } catch (e) {
        console.warn('⚠️  Dev-Autoloader konnte nicht wiederhergestellt werden — manuell: composer dump-autoload --optimize');
    }
}

// Create ZIP archive
console.log(`\n📦 Creating WordPress Plugin ZIP...`);
console.log(`Plugin: ${pluginName}`);
console.log(`Previous Version: ${currentVersion}`);
console.log(`New Version: ${newVersion}`);
console.log(`Output: ${outputPath}\n`);

const output = fs.createWriteStream(outputPath);
const archive = archiver('zip', {
    zlib: { level: 9 } // Maximum compression
});

// Track statistics
let fileCount = 0;
let totalSize = 0;

// Listen to archive events
output.on('close', function() {
    const zipSize = archive.pointer();
    const zipSizeMB = (zipSize / 1024 / 1024).toFixed(2);
    const originalSizeMB = (totalSize / 1024 / 1024).toFixed(2);
    const compressionRatio = ((1 - zipSize / totalSize) * 100).toFixed(1);

    console.log(`\n✅ ZIP file created successfully!`);
    console.log(`   Files: ${fileCount}`);
    console.log(`   Original size: ${originalSizeMB} MB`);
    console.log(`   ZIP size: ${zipSizeMB} MB`);
    console.log(`   Compression: ${compressionRatio}%`);
    console.log(`\n📁 Location: ${outputPath}`);

    // List remaining ZIP files
    const remainingZips = fs.readdirSync(outputDir)
        .filter(file => file.startsWith(`${pluginName}-`) && file.endsWith('.zip'))
        .sort();
    console.log(`\n📚 Available ZIP versions (${remainingZips.length}):`);
    remainingZips.forEach(zip => console.log(`   - ${zip}`));

    console.log(`\n✨ Ready for WordPress upload!`);

    // Lokalen Dev-Autoloader (mit phpunit etc.) wiederherstellen
    restoreDevAutoloader();
});

output.on('error', function(err) {
    console.error('❌ Error creating ZIP:', err);
    restoreDevAutoloader();
    process.exit(1);
});

archive.on('warning', function(err) {
    if (err.code === 'ENOENT') {
        console.warn('⚠️  Warning:', err);
    } else {
        throw err;
    }
});

archive.on('error', function(err) {
    console.error('❌ Archive error:', err);
    process.exit(1);
});

archive.on('entry', function(entry) {
    fileCount++;
    if (entry.stats) {
        totalSize += entry.stats.size;
    }

    // Show progress every 10 files
    if (fileCount % 10 === 0) {
        process.stdout.write(`\r   Processing: ${fileCount} files...`);
    }
});

// Pipe archive data to the file
archive.pipe(output);

// Helper function to check if path should be excluded
function shouldExclude(filePath) {
    const relativePath = path.relative(__dirname, filePath);

    for (const pattern of excludePatterns) {
        if (pattern.includes('*')) {
            // Handle wildcard patterns - escape dots for literal matching
            // Use [^\\/]* instead of .* so wildcards only match within a single filename
            const escaped = pattern.replace(/\./g, '\\.').replace(/\*/g, '[^\\\\/]*');
            const regex = new RegExp('(^|[\\\\/])' + escaped + '$');
            if (regex.test(relativePath)) {
                return true;
            }
        } else {
            // Handle exact matches and directory names
            const parts = relativePath.split(path.sep);
            if (parts.includes(pattern) || relativePath === pattern) {
                return true;
            }
        }
    }

    return false;
}

// Helper function to add directory recursively
function addDirectoryToArchive(dirPath, archivePath) {
    if (!fs.existsSync(dirPath)) {
        console.warn(`⚠️  Path not found: ${dirPath}`);
        return;
    }

    const stats = fs.statSync(dirPath);

    if (stats.isFile()) {
        if (!shouldExclude(dirPath)) {
            archive.file(dirPath, { name: `${pluginName}/${archivePath}` });
        }
        return;
    }

    if (stats.isDirectory()) {
        if (shouldExclude(dirPath)) {
            return;
        }

        const items = fs.readdirSync(dirPath);

        for (const item of items) {
            const fullPath = path.join(dirPath, item);
            const archiveSubPath = path.join(archivePath, item).replace(/\\/g, '/');

            if (!shouldExclude(fullPath)) {
                if (fs.statSync(fullPath).isDirectory()) {
                    addDirectoryToArchive(fullPath, archiveSubPath);
                } else {
                    archive.file(fullPath, { name: `${pluginName}/${archiveSubPath}` });
                }
            }
        }
    }
}

// Add all included paths to archive
for (const includePath of includePaths) {
    const fullPath = path.join(__dirname, includePath);
    const archivePath = includePath;

    console.log(`   Adding: ${includePath}`);
    addDirectoryToArchive(fullPath, archivePath);
}

// Finalize the archive
archive.finalize();
