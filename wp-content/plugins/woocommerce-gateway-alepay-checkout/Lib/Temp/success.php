<?php
$data = $_SESSION['infos'];
$returnUrl = $_SESSION['returnUrl'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <!-- Compiled and minified CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/0.98.2/css/materialize.min.css">

    <!-- Compiled and minified JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/0.98.2/js/materialize.min.js"></script>
    <link rel="stylesheet" href="style/style.css">
    <title>Show Data</title>
</head>
<body>
<div id="container">
    <div class="row">
        <div class="col s3"></div>
        <div class="col s6 center">
            <h4>Kết quả</h4>
            <ul class="collection col-md-8">
                <?php
                    $data = array_diff($data, ['']);
                ?>
                <?php foreach ($data as $info => $val) { ?>
                    <li class="collection-item">
                        <?php if ($val !== "") { ?>
                            <p><?php echo $info . ' : ' . $val ?> </p>
                        <?php } ?>
                    </li>
                <?php } ?>
            </ul>
            <ul class="collection col-md-8">
                <li class="collection-item">
                    <div>
                        <a href="<?php echo $returnUrl ?>">Nhấn
                            Vào Đây Nếu Bạn Muốn Mua Tiếp</a>
                    </div>
                </li>
            </ul>

        </div>
    </div>
</div>
</body>
</html>
<?php session_destroy(); ?>
