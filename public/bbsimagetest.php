<?php
// 1. データベースへの接続設定
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

if (isset($_POST['body'])) {
  // POSTで送られてくるフォームパラメータ body がある場合

  $image_filename = null;

  // 💡 2. 画像が選択され、一時ファイルが存在するか確認
  if (isset($_FILES['image']) && !empty($_FILES['image']['tmp_name'])) {

    // 💡 3. mime_content_type() を使ってファイルの中身（バイナリ）を直接解析
    $mime_type = mime_content_type($_FILES['image']['tmp_name']);

    // 💡 4. 許可する画像タイプのホワイトリストを作成
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    // 💡 5. 解析したMIMEタイプが許可リストにない場合、処理を中断して安全にリダイレクト
    if (!in_array($mime_type, $allowed_types, true)) {
      header("HTTP/1.1 302 Found");
      header("Location: ./bbsimagetest.php");
      return;
    }

    // 💡 6. 元のファイル名から拡張子を取得（安全のため小文字化）
    $pathinfo = pathinfo($_FILES['image']['name']);
    $extension = strtolower(isset($pathinfo['extension']) ? $pathinfo['extension'] : 'jpg');

    // 💡 7. ファイル名の重複を防ぐため、時間 + 25バイトのランダム文字列でユニークなファイル名を生成
    $image_filename = strval(time()) . bin2hex(random_bytes(25)) . '.' . $extension;

    // 💡 8. 保存先ディレクトリ（Dockerボリューム共有先）へファイルを移動
    $filepath = '/var/www/upload/image/' . $image_filename;
    move_uploaded_file($_FILES['image']['tmp_name'], $filepath);
  }

  // 💡 9. データベースに投稿文と画像ファイル名をインサート
  $insert_sth = $dbh->prepare("INSERT INTO bbs_entries (body, image_filename) VALUES (:body, :image_filename)");
  $insert_sth->execute([
    ':body' => $_POST['body'],
    ':image_filename' => $image_filename,
  ]);

  // 💡 10. 二重投稿防止のためのリダイレクト (PRGパターン)
  header("HTTP/1.1 302 Found");
  header("Location: ./bbsimagetest.php");
  return;
}

// 💡 11. 保存済みの投稿データを新しい順に取得
$select_sth = $dbh->prepare('SELECT * FROM bbs_entries ORDER BY created_at DESC');
$select_sth->execute();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>画像付き掲示板</title>
</head>
<body>

<form method="POST" action="./bbsimagetest.php" enctype="multipart/form-data">
  <textarea name="body" required placeholder="投稿内容を入力してください"></textarea>
  <div style="margin: 1em 0;">
    <input type="file" accept="image/*" name="image">
  </div>
  <button type="submit">送信</button>
</form>

<script>
  // 💡 ファイル選択時のサイズチェック処理
  document.getElementById('imageInput').addEventListener('change', function(e) {
    const file = e.target.files[0];

    // ファイルが選択されている場合のみチェック
    if (file) {
      const maxSize = 5 * 1024 * 1024; // 5MB (バイト単位)

      if (file.size > maxSize) {
        alert('ファイルサイズが5MBを超えています。5MB以下の画像を選択してください。');

        // 💡 ファイル選択を強制解除（リセット）
        e.target.value = '';
      }
    }
  });
</script>

<hr>

<?php foreach($select_sth as $entry): ?>
  <dl style="margin-bottom: 1em; padding-bottom: 1em; border-bottom: 1px solid #ccc;">
    <dt>ID</dt>
    <dd><?= $entry['id'] ?></dd>
    <dt>日時</dt>
    <dd><?= $entry['created_at'] ?></dd>
    <dt>内容</dt>
    <dd>
      <?= nl2br(htmlspecialchars($entry['body'], ENT_QUOTES, 'UTF-8')) // XSS対策 ?>
      <?php if(!empty($entry['image_filename'])): // 画像が存在する場合は表示 ?>
        <div style="margin-top: 0.5em;">
          <img src="/image/<?= htmlspecialchars($entry['image_filename'], ENT_QUOTES, 'UTF-8') ?>" style="max-height: 10em;" alt="投稿画像">
        </div>
      <?php endif; ?>
    </dd>
  </dl>
<?php endforeach; ?>

</body>
</html>
