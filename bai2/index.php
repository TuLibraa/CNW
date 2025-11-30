<?php
// Đọc nội dung file
$lines = file("Quiz.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

$questions = [];
$current = [];

foreach ($lines as $line) {
    if (strpos($line, "ANSWER:") === 0) {
        $current['answer'] = trim(substr($line, 7)); 
        $questions[] = $current;
        $current = [];
    } else {
        if (!isset($current['question'])) {
            $current['question'] = $line;
        } else {
            $current['options'][] = $line;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quiz Trắc Nghiệm</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .question-box { 
            background: #f7f7f7; padding: 15px; margin-bottom: 20px; 
            border-radius: 8px; border: 1px solid #ddd;
        }
        h2 { margin-bottom: 5px; }
    </style>
</head>
<body>

<h1>📘 Bài Thi Trắc Nghiệm</h1>

<form>
    <?php foreach ($questions as $index => $q): ?>
        <div class="question-box">
            <h3>Câu <?= $index + 1 ?>: <?= $q['question'] ?></h3>

            <?php foreach ($q['options'] as $opt): ?>
                <label>
                    <input type="radio" name="q<?= $index ?>">
                    <?= $opt ?>
                </label>
                <br>
            <?php endforeach; ?>

            <p><b>Đáp án đúng:</b> <?= $q['answer'] ?></p>
        </div>
    <?php endforeach; ?>
</form>

</body>
</html>