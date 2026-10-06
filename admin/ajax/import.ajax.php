<?php
ob_start();
session_start();
header('Content-Type: application/json');

// Auth check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['import_file'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['import_file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'File upload error: ' . $file['error']]);
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$extractedText = '';

try {
    if ($ext === 'txt') {
        $content = file_get_contents($file['tmp_name']);
        // Convert to basic HTML paragraphs
        $paragraphs = explode("\n", str_replace("\r", "", $content));
        foreach ($paragraphs as $p) {
            $p = trim($p);
            if (!empty($p)) {
                $extractedText .= "<p>" . htmlspecialchars($p) . "</p>";
            }
        }
    } elseif ($ext === 'docx') {
        $zip = new ZipArchive;
        if ($zip->open($file['tmp_name']) === true) {
            if (($index = $zip->locateName('word/document.xml')) !== false) {
                $data = $zip->getFromIndex($index);
                $zip->close();
                
                $xml = new DOMDocument();
                // Suppress warnings for invalid XML fragments sometimes found in parsed docs
                @$xml->loadXML($data);
                $paragraphs = $xml->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'p');
                
                foreach ($paragraphs as $p) {
                    $text = '';
                    if ($p instanceof DOMElement) {
                        $texts = $p->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 't');
                        foreach ($texts as $t) {
                            $text .= $t->nodeValue;
                        }
                    }
                    if (trim($text) !== '') {
                        $extractedText .= "<p>" . htmlspecialchars($text) . "</p>";
                    }
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Could not find document.xml in docx file.']);
                exit;
            }
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Failed to open docx file. Ensure the ZipArchive PHP extension is enabled.']);
            exit;
        }
    } elseif ($ext === 'pdf') {
        // Basic fallback PDF text extraction. Pure PHP PDF extraction is notoriously unreliable due to formatting chunks, 
        // compression, and font mappings. This attempts to uncompress FlateDecode streams.
        $content = file_get_contents($file['tmp_name']);
        
        // Find streams
        if (preg_match_all('/stream(.*?)endstream/s', $content, $matches)) {
            $texts = [];
            foreach ($matches[1] as $stream) {
                $stream = trim($stream);
                // Try decompressing if it might be flate decoded
                $uncompressed = @gzuncompress($stream);
                if ($uncompressed !== false) {
                    $stream = $uncompressed;
                }
                
                // Very basic PDF text operator matching (...) Tj or (...) TJ
                if (preg_match_all('/\((.*?)\)\s*T[jJ]/s', $stream, $textMatches)) {
                    foreach ($textMatches[1] as $t) {
                        // strip basic PDF escapes
                        $t = str_replace(['\\\\', '\\(', '\\)'], ['\\', '(', ')'], $t);
                        $texts[] = htmlspecialchars($t);
                    }
                } elseif (preg_match_all('/\[(.*?)\]\s*TJ/s', $stream, $arrayMatches)) {
                    foreach ($arrayMatches[1] as $arr) {
                        if (preg_match_all('/\((.*?)\)/s', $arr, $textMatches)) {
                            foreach ($textMatches[1] as $t) {
                                $t = str_replace(['\\\\', '\\(', '\\)'], ['\\', '(', ')'], $t);
                                $texts[] = htmlspecialchars($t);
                            }
                        }
                    }
                }
            }
            if (count($texts) > 0) {
                // Group extracted fragments into basic paragraphs (heuristic)
                $combined = implode(" ", $texts);
                $extractedText = "<p>" . $combined . "</p>";
                $extractedText .= "<p><em>Note: PDF extraction is experimental and may miss formatting. Please verify the imported text.</em></p>";
            } else {
                throw new Exception("Could not extract any readable text streams from PDF. Formatting may be unsupported. Please use DOCX or TXT instead.");
            }
        } else {
            throw new Exception("No extractable streams found in PDF.");
        }
    } else {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Unsupported file format']);
        exit;
    }
    
    ob_clean();
    echo json_encode(['success' => true, 'text' => $extractedText]);
} catch (Throwable $e) { // Catch Throwable to grab errors and exceptions
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
