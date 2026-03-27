<?php
/**
 * CBD PDF Debug - Direct browser access to test PDF capabilities
 * Access via: https://chemiefos.fos-meran.it/wp-content/plugins/container-block-designer/debug-pdf.php
 * DELETE THIS FILE after debugging!
 */

// Load WordPress
$wp_load_paths = array(
    dirname(__FILE__) . '/../../../wp-load.php',
    dirname(__FILE__) . '/../../../../wp-load.php',
);

$loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $loaded = true;
        break;
    }
}

header('Content-Type: application/json; charset=utf-8');

if (!$loaded) {
    echo json_encode(array('error' => 'wp-load.php not found'));
    exit;
}

$results = array();

// Step 1: PHP Info
$results['php_version'] = PHP_VERSION;
$results['memory_limit'] = ini_get('memory_limit');
$results['post_max_size'] = ini_get('post_max_size');
$results['max_execution_time'] = ini_get('max_execution_time');
$results['display_errors'] = ini_get('display_errors');

// Step 2: Extensions
$results['ext_mbstring'] = extension_loaded('mbstring');
$results['ext_gd'] = extension_loaded('gd');
$results['ext_zlib'] = extension_loaded('zlib');
$results['ext_xml'] = extension_loaded('xml');

// Step 3: Plugin directory
$results['plugin_dir'] = dirname(__FILE__);
$results['vendor_autoload_exists'] = file_exists(dirname(__FILE__) . '/vendor/autoload.php');

// Step 4: Try loading autoloader
$results['autoloader_loaded'] = false;
$results['autoloader_error'] = '';
try {
    if (!class_exists('Composer\Autoload\ClassLoader')) {
        require_once dirname(__FILE__) . '/vendor/autoload.php';
    }
    $results['autoloader_loaded'] = true;
} catch (\Throwable $e) {
    $results['autoloader_error'] = $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine();
}

// Step 5: Try class_exists for mPDF
$results['mpdf_class_exists'] = false;
$results['mpdf_class_error'] = '';
try {
    $results['mpdf_class_exists'] = class_exists('\\Mpdf\\Mpdf');
} catch (\Throwable $e) {
    $results['mpdf_class_error'] = $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine();
}

// Step 6: Try class_exists for TCPDF
$results['tcpdf_class_exists'] = false;
$results['tcpdf_class_error'] = '';
try {
    $results['tcpdf_class_exists'] = class_exists('TCPDF');
} catch (\Throwable $e) {
    $results['tcpdf_class_error'] = $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine();
}

// Step 7: Try creating temp directory
$upload_dir = wp_upload_dir();
$temp_dir = $upload_dir['basedir'] . '/cbd-temp-pdfs/';
$results['upload_basedir'] = $upload_dir['basedir'];
$results['temp_dir'] = $temp_dir;
$results['temp_dir_exists'] = file_exists($temp_dir);

if (!file_exists($temp_dir)) {
    $results['temp_dir_created'] = wp_mkdir_p($temp_dir);
} else {
    $results['temp_dir_created'] = true;
}
$results['temp_dir_writable'] = is_writable($temp_dir);

// Step 8: Try creating mPDF instance
$results['mpdf_instance'] = false;
$results['mpdf_instance_error'] = '';
if ($results['mpdf_class_exists']) {
    try {
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $temp_dir,
            'default_font' => 'dejavusans',
        ]);
        $results['mpdf_instance'] = true;
        $results['mpdf_version'] = \Mpdf\Mpdf::VERSION;
    } catch (\Throwable $e) {
        $results['mpdf_instance_error'] = $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine();
    }
}

// Step 9: Try generating a simple PDF
$results['pdf_generated'] = false;
$results['pdf_error'] = '';
if ($results['mpdf_instance']) {
    try {
        $mpdf->WriteHTML('<h1>Test PDF</h1><p>This is a test.</p>');
        $test_file = $temp_dir . 'test-' . time() . '.pdf';
        $mpdf->Output($test_file, \Mpdf\Output\Destination::FILE);
        $results['pdf_generated'] = file_exists($test_file);
        $results['pdf_size'] = filesize($test_file);
        // Cleanup test file
        @unlink($test_file);
    } catch (\Throwable $e) {
        $results['pdf_error'] = $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine();
    }
}

// Step 10: Check if CBD_PDF_Generator loads
$results['cbd_pdf_generator'] = false;
$results['cbd_pdf_generator_error'] = '';
try {
    if (class_exists('CBD_PDF_Generator')) {
        $gen = CBD_PDF_Generator::get_instance();
        $results['cbd_pdf_generator'] = true;
        $results['cbd_pdf_engine'] = $gen->get_engine();
    } else {
        $results['cbd_pdf_generator_error'] = 'Class CBD_PDF_Generator not found';
    }
} catch (\Throwable $e) {
    $results['cbd_pdf_generator_error'] = $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine();
}

// Step 11: Check last PHP error
$last_error = error_get_last();
if ($last_error) {
    $results['last_php_error'] = $last_error;
}

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
