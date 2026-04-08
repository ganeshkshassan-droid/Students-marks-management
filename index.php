<?php
// config.php - Database Configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "student_marks_system";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}



session_start();

// Authentication functions
function loginStudent($usn, $password, $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE usn = ?");
    $stmt->execute([$usn]);
    $student = $stmt->fetch();
    if ($student && password_verify($password, $student['password'])) {
        $_SESSION['student_usn'] = $student['usn'];
        $_SESSION['student_name'] = $student['name'];
        return true;
    }
    return false;
}

function loginAdmin($username, $password, $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_name'] = $admin['name'];
        return true;
    }
    return false;
}

function registerStudent($usn, $name, $password, $pdo) {
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    try {
        $stmt = $pdo->prepare("INSERT INTO students (usn, name, password) VALUES (?, ?, ?)");
        $stmt->execute([$usn, $name, $hashedPassword]);
        return true;
    } catch(PDOException $e) {
        return false;
    }
}

// Handle form submissions
if ($_POST) {
    if (isset($_POST['student_login'])) {
        if (loginStudent($_POST['usn'], $_POST['password'], $pdo)) {
            header('Location: ?page=student_dashboard');
            exit;
        } else {
            $error = "Invalid USN or password";
        }
    }
    if (isset($_POST['student_register'])) {
        if (registerStudent($_POST['usn'], $_POST['name'], $_POST['password'], $pdo)) {
            $success = "Registration successful! Please login.";
        } else {
            $error = "Registration failed. USN might already exist.";
        }
    }
    if (isset($_POST['admin_login'])) {
        if (loginAdmin($_POST['username'], $_POST['password'], $pdo)) {
            header('Location: ?page=admin_dashboard');
            exit;
        } else {
            $error = "Invalid username or password";
        }
    }
    if (isset($_POST['add_subject'])) {
        $stmt = $pdo->prepare("INSERT INTO subjects (subject_code, subject_name, semester, credits) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['subject_code'], $_POST['subject_name'], $_POST['semester'], $_POST['credits']]);
        $success = "Subject added successfully!";
    }
    if (isset($_POST['upload_marks'])) {
        $stmt = $pdo->prepare("INSERT INTO results (student_usn, subject_id, semester, internal_marks, external_marks, total_marks, grade) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE internal_marks=VALUES(internal_marks), external_marks=VALUES(external_marks), total_marks=VALUES(total_marks), grade=VALUES(grade)");
        $total = $_POST['internal_marks'] + $_POST['external_marks'];
        $grade = calculateGrade($total);
        $stmt->execute([$_POST['student_usn'], $_POST['subject_id'], $_POST['semester'], $_POST['internal_marks'], $_POST['external_marks'], $total, $grade]);
        $success = "Marks uploaded successfully!";
    }
    if (isset($_POST['publish_results'])) {
        $stmt = $pdo->prepare("UPDATE results SET is_published = TRUE WHERE semester = ?");
        $stmt->execute([$_POST['semester']]);
        $success = "Results published for semester " . $_POST['semester'];
    }
    if (isset($_POST['delete_subject']) && isset($_POST['subject_id'])) {
        // First, delete all results for this subject
        $stmt = $pdo->prepare("DELETE FROM results WHERE subject_id = ?");
        $stmt->execute([$_POST['subject_id']]);
        // Then, delete the subject itself
        $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
        $stmt->execute([$_POST['subject_id']]);
        $success = "Subject and its related results deleted successfully!";
    }
}

function calculateGrade($marks) {
    if ($marks >= 90) return 'S';
    if ($marks >= 80) return 'A';
    if ($marks >= 70) return 'B';
    if ($marks >= 60) return 'C';
    if ($marks >= 50) return 'D';
    return 'F';
}

$page = $_GET['page'] ?? 'home';
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Marks Management System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { text-align: center; color: white; margin-bottom: 30px; padding: 20px; }
        .header h1 { font-size: 2.5rem; margin-bottom: 10px; text-shadow: 2px 2px 4px rgba(0,0,0,0.3); }
        .nav {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 30px;
            display: flex;
            justify-content: center;
            gap: 20px;
        }
        .nav a {
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-weight: 600;
        }
        .nav a:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            margin: 20px 0;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.2);
        }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 12px 15px; border: 2px solid #e1e5e9; border-radius: 10px;
            font-size: 1rem; transition: all 0.3s ease; background: rgba(255,255,255,0.9);
        }
        .form-group input:focus, .form-group select:focus {
            outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; border: none; padding: 12px 30px; border-radius: 25px; cursor: pointer;
            font-size: 1rem; font-weight: 600; transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4); }
        .btn-secondary {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            box-shadow: 0 4px 15px rgba(245, 87, 108, 0.3);
        }
        .btn-success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            box-shadow: 0 4px 15px rgba(79, 172, 254, 0.3);
        }
        .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; font-weight: 500; }
        .alert-error { background: #fee; color: #c33; border: 1px solid #fcc; }
        .alert-success { background: #efe; color: #363; border: 1px solid #cfc; }
        .tab-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 30px;
            color: #111;
        }
        .tab-btn {
            background: rgba(255, 255, 255, 0.2);
            color: #111;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .tab-btn.active {
            background: rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 15px rgba(255, 255, 255, 0.2);
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .results-grid { display: grid; gap: 20px; }
        .semester-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; padding: 25px; border-radius: 15px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        .semester-card h3 { margin-bottom: 15px; font-size: 1.5rem; }
        .subjects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .subject-card {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }
        .marks-display { display: flex; justify-content: space-between; margin-top: 10px; }
        .grade {
            font-size: 1.5rem; font-weight: bold; text-align: center; padding: 10px;
            border-radius: 50%; background: rgba(255, 255, 255, 0.2);
            width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;
        }
        .admin-actions { display: flex; gap: 15px; margin-top: 20px; flex-wrap: wrap; }
        .logout {
            position: fixed; top: 20px; right: 20px;
            background: rgba(255, 255, 255, 0.2); color: white;
            padding: 10px 20px; border-radius: 25px; text-decoration: none; font-weight: 600;
            transition: all 0.3s ease;
        }
        .logout:hover { background: rgba(255, 255, 255, 0.3); transform: translateY(-2px); }
        @media (max-width: 768px) {
            .container { padding: 10px; }
            .header h1 { font-size: 2rem; }
            .nav { flex-direction: column; align-items: center; }
            .card { padding: 20px; }
            .subjects-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎓 Student Marks Management System</h1>
            <p>Comprehensive Academic Performance Tracking</p>
        </div>

        <?php if (isset($_SESSION['student_usn']) || isset($_SESSION['admin_username'])): ?>
            <a href="?logout=1" class="logout">Logout</a>
        <?php endif; ?>

        <?php
        if (isset($_GET['logout'])) {
            session_destroy();
            header('Location: index.php');
            exit;
        }
        if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($page === 'home' && !isset($_SESSION['student_usn']) && !isset($_SESSION['admin_username'])): ?>
            <div class="nav">
                <a href="#" onclick="showTab('student-login')">Student Login</a>
                <a href="#" onclick="showTab('student-register')">Student Register</a>
                <a href="#" onclick="showTab('admin-login')">Admin Login</a>
            </div>
            <!-- Student Login -->
            <div id="student-login" class="card tab-content active">
                <h2>Student Login</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>USN (University Seat Number):</label>
                        <input type="text" name="usn" required placeholder="Enter your USN">
                    </div>
                    <div class="form-group">
                        <label>Password:</label>
                        <input type="password" name="password" required placeholder="Enter your password">
                    </div>
                    <button type="submit" name="student_login" class="btn">Login</button>
                </form>
            </div>
            <!-- Student Register -->
            <div id="student-register" class="card tab-content">
                <h2>Student Registration</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>USN (University Seat Number):</label>
                        <input type="text" name="usn" required placeholder="Enter your USN">
                    </div>
                    <div class="form-group">
                        <label>Full Name:</label>
                        <input type="text" name="name" required placeholder="Enter your full name">
                    </div>
                    <div class="form-group">
                        <label>Password:</label>
                        <input type="password" name="password" required placeholder="Create a password">
                    </div>
                    <button type="submit" name="student_register" class="btn btn-secondary">Register</button>
                </form>
            </div>
            <!-- Admin Login -->
            <div id="admin-login" class="card tab-content">
                <h2>Admin Login</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Username:</label>
                        <input type="text" name="username" required placeholder="Enter admin username">
                    </div>
                    <div class="form-group">
                        <label>Password:</label>
                        <input type="password" name="password" required placeholder="Enter admin password">
                    </div>
                    <button type="submit" name="admin_login" class="btn btn-success">Admin Login</button>
                </form>
            </div>
        <?php elseif (isset($_SESSION['student_usn'])): ?>
            <!-- Student Dashboard -->
            <div class="card">
                <h2>Welcome, <?php echo htmlspecialchars($_SESSION['student_name']); ?>!</h2>
                <p><strong>USN:</strong> <?php echo htmlspecialchars($_SESSION['student_usn']); ?></p>
                <div class="results-grid">
                    <?php
                    $stmt = $pdo->prepare("
                        SELECT r.*, s.subject_name, s.subject_code, s.credits 
                        FROM results r 
                        JOIN subjects s ON r.subject_id = s.id 
                        WHERE r.student_usn = ? AND r.is_published = TRUE 
                        ORDER BY r.semester, s.subject_code
                    ");
                    $stmt->execute([$_SESSION['student_usn']]);
                    $results = $stmt->fetchAll();
                    $semesters = [];
                    foreach ($results as $result) {
                        $semesters[$result['semester']][] = $result;
                    }
                    if (empty($semesters)): ?>
                        <div class="alert alert-error">No published results found. Please contact your admin.</div>
                    <?php else:
                        foreach ($semesters as $sem => $subjects): ?>
                            <div class="semester-card">
                                <h3>Semester <?php echo $sem; ?></h3>
                                <div class="subjects-grid">
                                    <?php foreach ($subjects as $subject): ?>
                                        <div class="subject-card">
                                            <h4><?php echo htmlspecialchars($subject['subject_name']); ?></h4>
                                            <p><strong>Code:</strong> <?php echo htmlspecialchars($subject['subject_code']); ?></p>
                                            <div class="marks-display">
                                                <div>
                                                    <p><strong>Internal:</strong> <?php echo $subject['internal_marks']; ?></p>
                                                    <p><strong>External:</strong> <?php echo $subject['external_marks']; ?></p>
                                                    <p><strong>Total:</strong> <?php echo $subject['total_marks']; ?></p>
                                                </div>
                                                <div class="grade"><?php echo $subject['grade']; ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach;
                    endif; ?>
                </div>
            </div>
        <?php elseif (isset($_SESSION['admin_username'])): ?>
            <!-- Admin Dashboard -->
            <div class="card">
                <h2>Admin Dashboard - Welcome, <?php echo htmlspecialchars($_SESSION['admin_name']); ?>!</h2>
                <div class="tab-buttons">
                    <button class="tab-btn active" onclick="showTab('add-subject')">Add Subject</button>
                    <button class="tab-btn" onclick="showTab('upload-marks')">Upload Marks</button>
                    <button class="tab-btn" onclick="showTab('publish-results')">Publish Results</button>
                    <button class="tab-btn" onclick="showTab('view-students')">View Students</button>
                </div>
                <!-- Add Subject -->
                <div id="add-subject" class="tab-content active">
                    <h3>Add New Subject</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label>Subject Code:</label>
                            <input type="text" name="subject_code" required placeholder="e.g., MCA101">
                        </div>
                        <div class="form-group">
                            <label>Subject Name:</label>
                            <input type="text" name="subject_name" required placeholder="e.g., Programming in C">
                        </div>
                        <div class="form-group">
                            <label>Semester:</label>
                            <select name="semester" required>
                                <option value="1">Semester 1</option>
                                <option value="2">Semester 2</option>
                                <option value="3">Semester 3</option>
                                <option value="4">Semester 4</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Credits:</label>
                            <input type="number" name="credits" value="4" required>
                        </div>
                        <button type="submit" name="add_subject" class="btn">Add Subject</button>
                    </form>
                    <h4 style="margin-top:30px;">Existing Subjects</h4>
                    <table style="width:100%;margin-top:15px;border-collapse:collapse;">
                        <tr style="background:#f3f3f3;">
                            <th style="padding:8px;">Code</th>
                            <th style="padding:8px;">Name</th>
                            <th style="padding:8px;">Semester</th>
                            <th style="padding:8px;">Credits</th>
                            <th style="padding:8px;">Action</th>
                        </tr>
                        <?php
                        $stmt = $pdo->query("SELECT * FROM subjects ORDER BY semester, subject_code");
                        while ($subject = $stmt->fetch()):
                        ?>
                        <tr>
                            <td style="padding:8px;"><?php echo htmlspecialchars($subject['subject_code']); ?></td>
                            <td style="padding:8px;"><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                            <td style="padding:8px;"><?php echo htmlspecialchars($subject['semester']); ?></td>
                            <td style="padding:8px;"><?php echo htmlspecialchars($subject['credits']); ?></td>
                            <td style="padding:8px;">
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this subject?');">
                                    <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                    <button type="submit" name="delete_subject" class="btn btn-secondary" style="background:#c33;color:#fff;">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </table>
                </div>
                <!-- Upload Marks -->
                <div id="upload-marks" class="tab-content">
                    <h3>Upload Student Marks</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label>Student USN:</label>
                            <select name="student_usn" required>
                                <option value="">Select Student</option>
                                <?php
                                $stmt = $pdo->query("SELECT usn, name FROM students ORDER BY name");
                                while ($student = $stmt->fetch()) {
                                    echo "<option value='{$student['usn']}'>{$student['name']} ({$student['usn']})</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Subject:</label>
                            <select name="subject_id" required>
                                <option value="">Select Subject</option>
                                <?php
                                $stmt = $pdo->query("SELECT id, subject_name, subject_code, semester FROM subjects ORDER BY semester, subject_code");
                                while ($subject = $stmt->fetch()) {
                                    echo "<option value='{$subject['id']}'>Sem {$subject['semester']} - {$subject['subject_code']} - {$subject['subject_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Semester:</label>
                            <select name="semester" required>
                                <option value="1">Semester 1</option>
                                <option value="2">Semester 2</option>
                                <option value="3">Semester 3</option>
                                <option value="4">Semester 4</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Internal Marks (0-30):</label>
                            <input type="number" name="internal_marks" min="0" max="30" required>
                        </div>
                        <div class="form-group">
                            <label>External Marks (0-70):</label>
                            <input type="number" name="external_marks" min="0" max="70" required>
                        </div>
                        <button type="submit" name="upload_marks" class="btn">Upload Marks</button>
                    </form>
                </div>
                <!-- Publish Results -->
                <div id="publish-results" class="tab-content">
                    <h3>Publish Results</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label>Select Semester to Publish:</label>
                            <select name="semester" required>
                                <option value="1">Semester 1</option>
                                <option value="2">Semester 2</option>
                                <option value="3">Semester 3</option>
                                <option value="4">Semester 4</option>
                            </select>
                        </div>
                        <button type="submit" name="publish_results" class="btn btn-success" onclick="return confirm('Are you sure you want to publish these results? Students will be able to view them.')">Publish Results</button>
                    </form>
                    <h4>Published Status by Semester:</h4>
                    <?php
                    for ($i = 1; $i <= 4; $i++) {
                        $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(is_published) as published FROM results WHERE semester = ?");
                        $stmt->execute([$i]);
                        $status = $stmt->fetch();
                        echo "<p><strong>Semester $i:</strong> {$status['published']} of {$status['total']} results published</p>";
                    }
                    ?>
                </div>
                <!-- View Students -->
                <div id="view-students" class="tab-content">
                    <h3>Registered Students</h3>
                    <?php
                    $stmt = $pdo->query("SELECT * FROM students ORDER BY name");
                    $students = $stmt->fetchAll();
                    ?>
                    <div class="subjects-grid">
                        <?php foreach ($students as $student): ?>
                            <div class="subject-card" style="background: rgba(102, 126, 234, 0.1); color: #333;">
                                <h4><?php echo htmlspecialchars($student['name']); ?></h4>
                                <p><strong>USN:</strong> <?php echo htmlspecialchars($student['usn']); ?></p>
                                <p><strong>Registered:</strong> <?php echo date('d M Y', strtotime($student['created_at'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <script>
        function showTab(tabId) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(function(content) {
                content.classList.remove('active');
            });
            // Remove active class from all buttons
            document.querySelectorAll('.tab-btn').forEach(function(btn) {
                btn.classList.remove('active');
            });
            // Show selected tab
            document.getElementById(tabId).classList.add('active');
            // Add active class to clicked button
            event.target.classList.add('active');
        }
        // Add smooth animations
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.card, .semester-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>