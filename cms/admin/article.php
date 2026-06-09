<?php
declare(strict_types = 1);                                 
include '../includes/database-connection.php';           
include '../includes/functions.php';                     
include '../includes/validate.php';

// 경로 수정: .DIRECTORY 제거 및 DIREXTORY_SEPARATOR 오타 교정
$uploads = dirname(__DIR__, 1) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
$file_types = ['image/jpeg', 'image/png', 'image/gif']; // MIME 타입 형식 표준화
$file_exts  = ['jpg', 'jpeg', 'png', 'gif'];
$max_size = 5242880;

// 점(.)을 쉼표(,)로 교정
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$temp = $_FILES['image']["tmp_name"] ?? '';
$destination = '';

$article = [
  'id'  => $id, 'title' => '',
  'summary' => '', 'content' => '',
  'member_id' => 0, 'category_id' => 0, // mamber_id 오타 수정
  'image_id' => null, 'published' => false,
  'image_file' => '', 'image_alt' => '', // iamge_alt 오타 수정
];

$errors = [
  'warning'=> '', 'title' => '', 'summary' => '', 'content' => '', // warring, countent 오타 수정
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
      redirect('article.php', ['failure' => 'Article not found']); // artilcle.php 오타 수정
    }   
}

$saved_image = !empty($article['image_file']) ? true : false;

$sql = "SELECT id, forename, surname FROM member;"; // memver -> member 오타 수정
$authors = pdo($pdo, $sql)->fetchAll();
$sql = "SELECT id, name FROM category;";
$categories = pdo($pdo, $sql)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') { // REQUST_METHOD 오타 수정
  $errors['image_file'] = ($_FILES['image']['error'] === 1) ? 'File too big' : '';

  if($temp and $_FILES['image']['error'] === 0){
    // 원본 코드의 치명적 대입 오류 수정 (alt 텍스트를 파일명 변수에 넣고 있던 로직 수정)
    $article['image_alt'] = $_POST['image_alt']; 

    // mime_content_type 오타 수정
    $errors['image_file'] .= in_array(mime_content_type($temp), $file_types)
      ? '' : 'Wrong file type. ';
    
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    // 비교 대상을 $file_types에서 $file_exts로 변경
    $errors['image_file'] .= in_array($ext, $file_exts)
      ? '' : 'Wrong file extension.';
    
    $errors['image_file'] .= ($_FILES['image']['size'] <= $max_size)
      ? '' : 'File too big. ';
    
    $errors['image_alt'] .= (is_text($article['image_alt'], 1, 254)) // alticle 오타 수정
      ? '' : 'Alt text must be 1-254 characters. ';

      if($errors['image_file'] === '' and $errors['image_alt'] === '') {
        $article['image_file'] = create_filename($_FILES['image']['name'], $uploads); // iamge_file 오타 수정
        $destination = $uploads . $article['image_file'];
      }
  }

  $article['title']       = $_POST['title'];
  $article['summary']     = $_POST['summary'];
  $article['content']     = $_POST['content'];
  $article['member_id']   = $_POST['member_id'];
  $article['category_id'] = $_POST['category_id']; // catecory_id 오타 수정
  $article['published']   = (isset($_POST['published']) and ($_POST['published'] == 1)) ? 1 : 0;

  $errors['title']   = is_text($article['title'], 1, 80) ? '' : 'Title must be 1-80 characters';
  $errors['summary'] = is_text($article['summary'], 1, 254) ? '' : 'Summary must be 1-254 characters';
  $errors['content'] = is_text($article['content'], 1, 100000) ? '' : 'Content must be 1-100000 characters';
  $errors['author']  = is_member_id($article['member_id'], $authors) ? '' : 'Please select an author'; // $errors['member'] 키 수정
  $errors['category'] = is_category_id($article['category_id'], $categories) ? '' : 'Please select a category';
    
  // invailid -> invalid 오타 수정 및 빈 문자열 결합 처리
  $invalid = implode('', $errors); 
}

// 검증 로직 진입 조건 명확화
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $invalid !== '') {
  $errors['warning'] = 'Please correct the errors below'; // warring 오타 수정
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $arguments = $article;
  try {
    $pdo->beginTransaction();
    if ($destination) {
      $imagick = new \Imagick($temp); // Imagick 대소문자 교정
      $imagick->cropThumbnailImage(1200, 700); // cropThumbnailImage 대소문자 교정
      $imagick->writeImage($destination);
      
      $sql = "INSERT INTO image (file, alt) VALUES (:file, :alt);";
      pdo($pdo, $sql, ['file' => $arguments['image_file'], 'alt' => $arguments['image_alt']]);
      $arguments['image_id'] = $pdo->lastInsertId(); // lastInsertId 대소문자 교정
    }
    
    // DB 쿼리에 쓸 인자만 남기기 위해 파일명 변수 분리 후 제거
    unset($arguments['image_file'], $arguments['image_alt']);
    
    if($id) {
      // UPDATE 문 syntax error 전면 수정
      $sql = "UPDATE article
                 SET title = :title, summary = :summary, content = :content,
                     category_id = :category_id, member_id = :member_id,
                     image_id = :image_id, published = :published
               WHERE id = :id;";
    } else {
      unset($arguments['id']);
      // INSERT 문 내 tile, publoshed 오타 전면 수정
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