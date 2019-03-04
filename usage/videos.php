<?php

use MirazMac\FackTube\FackTube;

require __DIR__ . '/../vendor/autoload.php';

$options = [];
$fack = new FackTube($options);

$pageToken = isset($_GET['page']) ? trim($_GET['page']) : null;
$q = isset($_GET['q']) ? trim($_GET['q']) : 'Honest Trailer';

try {
    $results = $fack->videos($q, $pageToken);
} catch (\Exception $e) {
    echo $e->getMessage();
    exit();
}
?>
<form method="get" action="?">
    <label for="q">Query</label>
    <input type="text" name="q" id="q" value="<?php echo htmlspecialchars($q); ?>">
    <button type="submit">Search</button>
</form>
<?php
foreach ($results['videos'] as $video) {
    echo $video['title'];
    echo "<hr/>";
}
echo "<br><ul>";
foreach ($results['pages'] as $page) {
    echo '<li><a href="?page=' . $page['pageToken'] . '&q=' . htmlspecialchars($q) . '">' . $page['label'] . '</a></li>';
}
echo "</ul>";
