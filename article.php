<?php
declare(strict_types = 1);                                 
include '../includes/database-connection.php';           
include '../includes/functions.php';                     
include '../includes/validate.php';

$uploads = dirname(__DIR__, 1) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
$file_types = ['image/jpeg', 'image/png', 'image/gif'];
$file_exts  = ['jpg', 'jpeg', 'png', 'gif'];
$max_size = 5242880;

// 점(.)을 쉼표(,)로 교정
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$temp = $_FILES['image']["tmp_name"] ?? '';
$destination = '';

$article = [
  'id'  => $id, 'title' => '',
  'summary' => '', 'content' => '',
  'member_id' => 0, 'category_id' => 0, 
  'image_id' => null, 'published' => false,
  'image_file' => '', 'image_alt' => '', 
];

$errors = [
  'warning'=> '', 'title' => '', 'summary' => '', 'content' => '', 
  'author' => '', 'category' => '', 'image_file' => '', 'image_alt' => '', 
];

if($id) {
   $sql = "SELECT a.id, a.title , a.summary, a.content,
            a.category_id, a.member_id, a.image_id, a.published,
            i.file  AS image_file,
            i.alt   AS image_alt
              FROM article  AS a
              LEFT JOIN image AS i ON a.image_id = i.id
             WHERE a.id = :id;";                                          
    $article = pdo($pdo, $sql, [$id])->fetch();
    if(!$article) {
      redirect('article.php', ['failure' => 'Article not found']); 
    }   
}

$saved_image = !empty($article['image_file']) ? true : false;

$sql = "SELECT id, forename, surname FROM member;";
$authors = pdo($pdo, $sql)->fetchAll();
$sql = "SELECT id, name FROM category;";
$categories = pdo($pdo, $sql)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') { 
  $errors['image_file'] = ($_FILES['image']['error'] === 1) ? 'File too big' : '';

  if($temp and $_FILES['image']['error'] === 0){
    $article['image_alt'] = $_POST['image_alt']; 

    $errors['image_file'] .= in_array(mime_content_type($temp), $file_types)
      ? '' : 'Wrong file type. ';
    
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $errors['image_file'] .= in_array($ext, $file_exts)
      ? '' : 'Wrong file extension.';
    
    $errors['image_file'] .= ($_FILES['image']['size'] <= $max_size)
      ? '' : 'File too big. ';
    
    $errors['image_alt'] .= (is_text($article['image_alt'], 1, 254)) 
      ? '' : 'Alt text must be 1-254 characters. ';

      if($errors['image_file'] === '' and $errors['image_alt'] === '') {
        $article['image_file'] = create_filename($_FILES['image']['name'], $uploads); 
        $destination = $uploads . $article['image_file'];
      }
  }

  $article['title']       = $_POST['title'];
  $article['summary']     = $_POST['summary'];
  $article['content']     = $_POST['content'];
  $article['member_id']   = $_POST['member_id'];
  $article['category_id'] = $_POST['category_id']; 
  $article['published']   = (isset($_POST['published']) and ($_POST['published'] == 1)) ? 1 : 0;

  $errors['title']   = is_text($article['title'], 1, 80) ? '' : 'Title must be 1-80 characters';
  $errors['summary'] = is_text($article['summary'], 1, 254) ? '' : 'Summary must be 1-254 characters';
  $errors['content'] = is_text($article['content'], 1, 100000) ? '' : 'Content must be 1-100000 characters';
  $errors['author']  = is_member_id($article['member_id'], $authors) ? '' : 'Please select an author';
  $errors['category'] = is_category_id($article['category_id'], $categories) ? '' : 'Please select a category';
    
  $invalid = implode('', $errors); 
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $invalid !== '') {
  $errors['warning'] = 'Please correct the errors below'; 
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $arguments = $article;
  try {
    $pdo->beginTransaction();
    if ($destination) {
      $imagick = new \Imagick($temp);
      $imagick->cropThumbnailImage(1200, 700);
      $imagick->writeImage($destination);
      
      $sql = "INSERT INTO image (file, alt) VALUES (:file, :alt);";
      pdo($pdo, $sql, ['file' => $arguments['image_file'], 'alt' => $arguments['image_alt']]);
      $arguments['image_id'] = $pdo->lastInsertId(); 
    }
    
    unset($arguments['image_file'], $arguments['image_alt']);
    
    if($id) {
      $sql = "UPDATE article
                 SET title = :title, summary = :summary, content = :content,
                     category_id = :category_id, member_id = :member_id,
                     image_id = :image_id, published = :published
               WHERE id = :id;";
    } else {
      unset($arguments['id']);
      $sql = "INSERT INTO article (title, summary, content, category_id, member_id, image_id, published)
              VALUES (:title, :summary, :content, :category_id, :member_id, :image_id, :published);";
    }
    
    pdo($pdo, $sql, $arguments);
    $pdo->commit();
    redirect('articles.php', ['success' => 'Article saved']);
  } catch (PDOException $e) {
    $pdo->rollBack();
    if(file_exists($destination)){
      unlink($destination);
    }
    if(isset($e->errorInfo[1]) && $e->errorInfo[1] === 1062){
      $errors['warning'] = 'Article title already used';
    } else {
      throw $e;
    }
  }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $article['image_file'] = $saved_image ? $article['image_file'] : '';
}
?>
<?php include '../includes/admin-header.php'; ?>
  <form action="article.php?id=<?= $id ?>" method="POST" enctype="multipart/form-data">
    <main class="container admin" id="content">

      <h1>Edit Article</h1>
      <?php if (!empty($errors['warning'])) { ?>
        <div class="alert alert-danger"><?= $errors['warning'] ?></div>
      <?php } ?>

      <div class="admin-article">
        <section class="image">
          <?php if (!$article['image_file']) { ?>
            <label for="image">Upload image:</label>
            <div class="form-group image-placeholder">
              <input type="file" name="image" class="form-control-file" id="image"><br>
              <span class="errors"><?= $errors['image_file'] ?></span>
            </div>
            <div class="form-group">
              <label for="image_alt">Alt text: </label>
              <input type="text" name="image_alt" id="image_alt" value="" class="form-control">
              <span class="errors"><?= $errors['image_alt'] ?></span>
            </div>
          <?php } else { ?>
            <label>Image:</label>
            <img src="../uploads/<?= html_escape($article['image_file']) ?>"
                 alt="<?= html_escape($article['image_alt']) ?>">
            <p class="alt"><strong>Alt text:</strong> <?= html_escape($article['image_alt']) ?></p>
            <a href="alt-text-edit.php?id=<?= $article['id'] ?>" class="btn btn-secondary">Edit alt text</a>
            <a href="image-delete.php?id=<?= $id ?>" class="btn btn-secondary">Delete image</a><br><br>
          <?php } ?>
        </section>

        <section class="text">
          <div class="form-group">
            <label for="title">Title: </label>
            <input type="text" name="title" id="title" value="<?= html_escape($article['title']) ?>"
                   class="form-control">
            <span class="errors"><?= $errors['title'] ?></span>
          </div>
          <div class="form-group">
            <label for="summary">Summary: </label>
            <textarea name="summary" id="summary"
                      class="form-control"><?= html_escape($article['summary']) ?></textarea>
            <span class="errors"><?= $errors['summary'] ?></span>
          </div>
          <div class="form-group">
            <label for="content">Content: </label>
            <textarea name="content" id="content"
                      class="form-control"><?= html_escape($article['content']) ?></textarea>
            <span class="errors"><?= $errors['content'] ?></span>
          </div>
          <div class="form-group">
            <label for="member_id">Author: </label>
            <select name="member_id" id="member_id">
              <?php foreach ($authors as $author) { ?>
                <option value="<?= $author['id'] ?>"
                    <?= ($article['member_id'] == $author['id']) ? 'selected' : ''; ?>>
                    <?= html_escape($author['forename'] . ' ' . $author['surname']) ?></option>
              <?php } ?>
            </select>
            <span class="errors"><?= $errors['author'] ?></span>
          </div>
          <div class="form-group">
            <label for="category">Category: </label>
            <select name="category_id" id="category">
              <?php foreach ($categories as $category) { ?>
                <option value="<?= $category['id'] ?>"
                    <?= ($article['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                    <?= html_escape($category['name']) ?></option>
              <?php } ?>
            </select>
            <span class="errors"><?= $errors['category'] ?></span>
          </div>
          <div class="form-check">
            <input type="checkbox" name="published" value="1" class="form-check-input" id="published"
                <?= ($article['published'] == 1) ? 'checked' : ''; ?>>
            <label for="published" class="form-check-label">Published</label>
          </div>
          <input type="submit" name="update" value="Save" class="btn btn-primary">
        </section>
      </div>
    </main>
  </form>
<?php include '../includes/admin-footer.php'; ?>