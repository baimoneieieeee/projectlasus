<?php
session_start();
include __DIR__ . '/config/db.php';

$error = "";
$isJson = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false;

if ($isJson) {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $conn->real_escape_string(trim($input['email'] ?? ''));
    $pass  = trim($input['password'] ?? '');
} else {
    $email = $conn->real_escape_string(trim($_POST['email'] ?? ''));
    $pass  = trim($_POST['password'] ?? '');
}

if ($email && $pass) {
    // ตรวจสอบเจ้าของร้าน
    $sql_shop = "SELECT * FROM shop WHERE shop_mail='$email' LIMIT 1";
    $result_shop = $conn->query($sql_shop);

    if ($result_shop && $result_shop->num_rows > 0) {
        $shop = $result_shop->fetch_assoc();
        if ($pass === $shop['shop_password']) {
            $_SESSION['user'] = $shop;
            $_SESSION['role'] = 'owner';
            header("Location: owner/owner_home.php");
            exit;
        } else {
            $error = "รหัสผ่านไม่ถูกต้องสำหรับเจ้าของร้าน";
        }
    } else {
        // ตรวจสอบสมาชิก
        $sql_member = "SELECT * FROM member WHERE m_mail='$email' LIMIT 1";
        $result_member = $conn->query($sql_member);

        if ($result_member && $result_member->num_rows > 0) {
            $member = $result_member->fetch_assoc();
            if ($pass === $member['m_password']) {
                $_SESSION['user'] = $member;
                $_SESSION['role'] = 'member';

                if (
                    empty($member['m_latitude']) || empty($member['m_longitude']) ||
                    $member['m_latitude'] == 0 || $member['m_longitude'] == 0
                ) {
                    $redirectUrl = "member/set_location.php";
                } else {
                    $redirectUrl = "member/home.php";
                }
                header("Location: " . $redirectUrl);
                exit;
            } else {
                $error = "รหัสผ่านไม่ถูกต้องสำหรับสมาชิก";
            }
        } else {
            $error = "ไม่พบอีเมลนี้ในระบบ";
        }
    }
}
?>
<!doctype html>
<html lang="th">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>เข้าสู่ระบบ - Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-1: #E6F4FF;
            /* sky mist */
            --bg-2: #D9ECFF;
            /* pastel blue */
            --panel: #FFFFFF;
            /* การ์ดขาวนวล */
            --border: #C8D9EE;
            --text: #1E293B;
            --text-soft: #64748B;
            --primary: #3B82F6;
            /* ฟ้าหลัก */
            --primary-600: #2563EB;
            /* hover ฟ้าเข้ม */
            --accent: #60A5FA;
            /* ฟ้ารอง */
            --radius: 16px;

            --shadow: 0 12px 28px rgba(30, 41, 59, .08), 0 6px 14px rgba(37, 99, 235, .08);
            --shadow-raise: 0 16px 36px rgba(30, 41, 59, .12), 0 8px 18px rgba(37, 99, 235, .10);
            --shadow-soft: 0 6px 16px rgba(30, 41, 59, .06);
        }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Noto Sans Thai', sans-serif;
            background:
                radial-gradient(700px 260px at 10% -10%, rgba(59, 130, 246, .10), transparent 60%),
                radial-gradient(800px 300px at 110% 10%, rgba(96, 165, 250, .12), transparent 60%),
                linear-gradient(135deg, var(--bg-1), var(--bg-2));
            color: var(--text);
        }

        .shell {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .cardx {
            max-width: 960px;
            width: 100%;
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: box-shadow .25s ease, transform .2s ease;
        }

        .cardx:hover {
            box-shadow: var(--shadow-raise);
            transform: translateY(-2px);
        }

        .hero {
            padding: 28px;
            background: linear-gradient(135deg, #EAF6FF, #DCEBFF);
            border-right: 1px solid var(--border);
        }

        .badgex {
            display: inline-block;
            padding: .4rem .75rem;
            border-radius: 999px;
            background: #fff;
            color: var(--primary-600);
            font-weight: 800;
            border: 1px solid rgba(59, 130, 246, .25);
            box-shadow: var(--shadow-soft);
        }

        .title {
            font-weight: 900;
            color: #1D4ED8
        }

        .muted {
            color: var(--text-soft)
        }

        .content {
            padding: 26px
        }

        .form-label {
            font-weight: 600;
            color: var(--text-soft)
        }

        .form-control {
            border-radius: 12px;
            padding: .85rem 1rem;
            border: 1px solid var(--border);
            background: #fff;
            box-shadow: var(--shadow-soft);
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 .25rem rgba(59, 130, 246, .25);
            outline: none;
        }

        .btn {
            border-radius: 12px;
            padding: .9rem 1rem;
            font-weight: 800;
            transition: transform .15s ease, box-shadow .2s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
            box-shadow: 0 8px 20px rgba(59, 130, 246, .25);
        }

        .btn-primary:hover {
            background: var(--primary-600);
            border-color: var(--primary-600);
            box-shadow: 0 10px 26px rgba(37, 99, 235, .28);
        }

        .btn-outline-success {
            color: var(--primary-600);
            border-color: var(--primary-600);
            background: #fff;
        }

        .btn-outline-success:hover {
            color: #fff;
            background: var(--primary-600);
            border-color: var(--primary-600);
            box-shadow: 0 8px 20px rgba(37, 99, 235, .25);
        }

        .foot {
            margin-top: 14px;
            color: var(--text-soft);
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            border-top: 1px solid var(--border);
            padding-top: 12px;
            font-size: .9rem;
        }
    </style>
</head>

<body>
    <h1>werwerwer</h1>
    <main class="shell">
        <section class="cardx">
            <div class="row g-0">
                <div class="col-12 col-lg-6 hero">
                    <span class="badgex mb-3">อนุชาซกอบรีด</span>
                    <h1 class="title h3 mb-2">ยินดีต้อนรับ 👕✨</h1>
                    <p class="muted mb-0">บริการร้านซักรีด บริการซักรีด</p>
                    <ul class="mt-3 mb-0 ps-3 muted small">
                        <!-- <li>ธีมฟ้าอ่อน + ฟ้าเข้ม</li>
                        <li>เงานุ่ม ลอยกำลังดี</li> -->
                    </ul>
                </div>
                <div class="col-12 col-lg-6 content">
                    <h2 class="h5 fw-bold mb-3">เข้าสู่ระบบ</h2>
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <form method="post" novalidate>
                        <div class="mb-3">
                            <label class="form-label" for="email">อีเมล</label>
                            <input class="form-control" id="email" name="email" type="email" placeholder="name@example.com" required autofocus>
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="password">รหัสผ่าน</label>
                            <input class="form-control" id="password" name="password" type="password" placeholder="••••••••" required>
                        </div>
                        <button class="btn btn-primary w-100 mt-2" type="submit" name="login">เข้าสู่ระบบ</button>
                        <a class="btn btn-outline-success w-100 mt-2" href="register.php">สมัครสมาชิก</a>
                    </form>
                    <div class="foot mt-2"><span>© 2025</span><span>Baimoneiei</span></div>
                </div>
            </div>
        </section>
    </main>
</body>

</html>