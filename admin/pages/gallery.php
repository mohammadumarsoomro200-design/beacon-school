<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Root path finding mechanism
$current_dir = __DIR__;
$root_dir = dirname(dirname($current_dir)); // Go up 2 levels from admin/pages

require_once $root_dir . '/config/config.php';
require_login();

if (!isset($db)) {
    if (isset($conn)) { $db = $conn; }
    elseif (isset($pdo)) { $db = $pdo; }
    elseif (function_exists('db')) { $db = db(); }
}

if (!isset(user()['role']) || strtolower(user()['role']) !== 'admin') {
    die("<div style='padding:20px; color:red;'><h2>Access Denied</h2></div>");
}

$page = 'Gallery Management';
$msg = '';
$error = '';

// Create Upload Directory physically
$upload_dir = $root_dir . '/uploads/gallery/';
if (!file_exists($upload_dir)) {
    @mkdir($upload_dir, 0777, true);
}

// Handle Delete Request via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_gallery_item') {
    ob_clean();
    header('Content-Type: application/json');
    $del_id = intval($_POST['delete_id'] ?? 0);
    try {
        $stmt = $db->prepare("SELECT * FROM gallery WHERE id = ?");
        $stmt->execute([$del_id]);
        $img = $stmt->fetch();
        if ($img) {
            $img_field = !empty($img['image_path']) ? $img['image_path'] : (!empty($img['image']) ? $img['image'] : '');
            $file_name = basename($img_field);
            $file_path = $upload_dir . $file_name;
            if (file_exists($file_path) && !is_dir($file_path)) {
                @unlink($file_path);
            }
            $del_stmt = $db->prepare("DELETE FROM gallery WHERE id = ?");
            $del_stmt->execute([$del_id]);
            echo json_encode(['success' => true]);
            exit;
        }
        echo json_encode(['success' => false, 'message' => 'Record not found']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle Upload Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $title = trim($_POST['title'] ?? '');
    
    if (isset($_FILES['image']['error']) && $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $error = "Upload failed! Error Code: " . $_FILES['image']['error'];
    } elseif (!empty($_FILES['image']['name'])) {
        $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $file_name = time() . '_' . rand(100, 999) . '.' . $file_ext;
        
        $target_file = $upload_dir . $file_name;
        // Clean database path format
        $db_image_path = 'uploads/gallery/' . $file_name;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            try {
                $cols = $db->query("DESCRIBE `gallery`")->fetchAll(PDO::FETCH_COLUMN);
                $col_title = in_array('title', $cols) ? 'title' : null;
                $col_img = in_array('image_path', $cols) ? 'image_path' : (in_array('image', $cols) ? 'image' : 'photo');

                if ($col_title) {
                    $stmt = $db->prepare("INSERT INTO gallery (`$col_title`, `$col_img`) VALUES (?, ?)");
                    $stmt->execute([$title, $db_image_path]);
                } else {
                    $stmt = $db->prepare("INSERT INTO gallery (`$col_img`) VALUES (?)");
                    $stmt->execute([$db_image_path]);
                }

                $msg = "Image successfully uploaded and saved!";
            } catch (Exception $e) {
                $error = "Database Insert Error: " . $e->getMessage();
            }
        } else {
            $error = "<b>Upload Failed!</b> System could not write file to: <br><code>" . htmlspecialchars($target_file) . "</code><br>Please check folder permissions.";
        }
    } else {
        $error = "Please choose an image to upload.";
    }
}

// Fetch Gallery Items
$gallery_items = [];
try {
    $stmt = $db->query("SELECT * FROM gallery ORDER BY id DESC");
    $gallery_items = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Failed to load gallery items: " . $e->getMessage();
}

// Include Header
if (file_exists(__DIR__ . '/../header.php')) {
    require_once __DIR__ . '/../header.php';
} elseif (file_exists(__DIR__ . '/header.php')) {
    require_once __DIR__ . '/header.php';
}
?>

<div style="max-width: 1100px; margin: 20px auto; font-family: sans-serif;">
    <h2>Gallery Management</h2>

    <?php if ($msg): ?>
        <div style="padding: 12px; background: #d1fae5; color: #065f46; border-radius: 6px; margin-bottom: 15px; font-weight: bold;"><?= $msg ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div style="padding: 12px; background: #fee2e2; color: #991b1b; border-radius: 6px; margin-bottom: 15px;"><?= $error ?></div>
    <?php endif; ?>

    <div style="display: flex; gap: 20px; flex-wrap: wrap;">
        <!-- Form Section -->
        <div style="flex: 1; min-width: 300px; background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0; height: fit-content;">
            <h3>Upload Gallery Image</h3>
            <form action="" method="POST" enctype="multipart/form-data">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">Title</label>
                    <input type="text" name="title" required style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">Image</label>
                    <input type="file" name="image" accept="image/*" required style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px;">
                </div>
                <button type="submit" style="background: #a16207; color: white; border: none; padding: 10px 20px; font-weight: bold; border-radius: 6px; cursor: pointer;">Upload</button>
            </form>
        </div>

        <!-- Display Section -->
        <div style="flex: 2; min-width: 300px; background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0;">
            <h3>Gallery</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 15px; margin-top: 15px;">
                <?php if (!empty($gallery_items)): ?>
                    <?php foreach ($gallery_items as $item): 
                        $raw_val = !empty($item['image_path']) ? $item['image_path'] : (!empty($item['image']) ? $item['image'] : (!empty($item['photo']) ? $item['photo'] : ''));
                        $file_name = basename($raw_val);
                        
                        // Universal relative image path
                        $web_img_path = "../../uploads/gallery/" . $file_name;
                    ?>
                        <div id="gallery-card-<?= $item['id'] ?>" style="border: 1px solid #e2e8f0; padding: 8px; border-radius: 8px; background: #f8fafc; text-align: center;">
                            <img src="<?= htmlspecialchars($web_img_path) ?>" 
                                 alt="<?= htmlspecialchars($item['title'] ?? 'Gallery Image') ?>" 
                                 style="width: 100%; height: 140px; object-fit: cover; border-radius: 6px; display: block;"
                                 onerror="this.onerror=null; this.src='../uploads/gallery/<?= htmlspecialchars($file_name) ?>'; this.onerror=function(){ this.parentElement.innerHTML='<div style=\'height:140px;background:#fee2e2;color:#991b1b;display:flex;align-items:center;justify-content:center;border-radius:6px;font-size:11px;\'>File missing in uploads/gallery/</div>'; };">

                            <h4 style="margin: 10px 0 5px 0; font-size: 14px; text-align: left; font-weight: bold; color: #1e293b;"><?= htmlspecialchars($item['title'] ?? 'Untitled') ?></h4>
                            <div style="text-align: left;">
                                <button type="button" onclick="deleteItem(<?= $item['id'] ?>)" style="background: none; border: none; color: #b91c1c; font-weight: bold; cursor: pointer; padding: 0; font-size: 13px;">Delete</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #64748b;">No images found in gallery.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function deleteItem(id) {
    if (confirm('Are you sure you want to delete this image?')) {
        const formData = new FormData();
        formData.append('action', 'delete_gallery_item');
        formData.append('delete_id', id);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const card = document.getElementById('gallery-card-' + id);
                if (card) { card.remove(); }
            } else {
                alert('Delete failed: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Delete request failed!');
        });
    }
}
</script>

<?php 
if (file_exists(__DIR__ . '/../footer.php')) {
    require_once __DIR__ . '/../footer.php';
} elseif (file_exists(__DIR__ . '/header.php')) {
    require_once __DIR__ . '/footer.php';
}
?>