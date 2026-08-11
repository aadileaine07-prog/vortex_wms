<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ==============================
   LOGIN CHECK
============================== */

if (!isset($_SESSION['user_id'])) {

    header("Location: ../../login.php");
    exit();

}

require_once "../../config/database.php";


/* ==============================
   GET LOGGED-IN USER
============================== */

$userId = (int) $_SESSION['user_id'];

$sql = "SELECT *
        FROM employees
        WHERE id = '$userId'
        LIMIT 1";

$result = mysqli_query($conn, $sql);

if (!$result) {

    die("Profile Query Failed: " . mysqli_error($conn));

}

if (mysqli_num_rows($result) !== 1) {

    die("Employee Profile Not Found.");

}

$profile = mysqli_fetch_assoc($result);


/* ==============================
   PROFILE PHOTO
============================== */

$photo = !empty($profile['photo'])
    ? $profile['photo']
    : 'default-profile.png';

?>

<?php include "../../includes/header.php"; ?>

<?php include "../../includes/navbar.php"; ?>

<?php include "../../includes/sidebar.php"; ?>


<div class="content">

    <div class="container-fluid">


        <!-- PAGE HEADER -->

        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>

                <h2 class="fw-bold mb-1">

                    <i class="fa-regular fa-user me-2"></i>

                    My Profile

                </h2>

                <p class="text-muted mb-0">

                    View your personal and employee information

                </p>

            </div>


            <a
                href="../../dashboard.php"
                class="btn btn-secondary">

                <i class="fa-solid fa-arrow-left me-1"></i>

                Back

            </a>

        </div>



        <!-- PROFILE -->

        <div class="row g-4">


            <!-- LEFT PROFILE CARD -->

            <div class="col-lg-4">

                <div class="card border-0 shadow-sm">

                    <div class="card-body text-center p-4">


                        <img

                            src="../../assets/images/employees/<?= htmlspecialchars($photo); ?>"

                            alt="Profile"

                            class="profile-main-image"

                            onerror="this.src='../../assets/images/profile.png';"

                        >


                        <h4 class="fw-bold mb-1">

                            <?= htmlspecialchars(
                                $profile['full_name'] ?? 'User'
                            ); ?>

                        </h4>


                        <p class="text-muted mb-2">

                            <?= htmlspecialchars(
                                $profile['designation'] ?? 'Employee'
                            ); ?>

                        </p>


                        <span class="badge bg-primary">

                            <?= htmlspecialchars(
                                $profile['role'] ?? 'Staff'
                            ); ?>

                        </span>


                        <hr class="my-4">


                        <div class="text-start">


                            <div class="profile-row">

                                <span>Employee ID</span>

                                <strong>
                                    <?= htmlspecialchars(
                                        $profile['employee_id'] ?? '-'
                                    ); ?>
                                </strong>

                            </div>


                            <div class="profile-row">

                                <span>Department</span>

                                <strong>
                                    <?= htmlspecialchars(
                                        $profile['department'] ?? '-'
                                    ); ?>
                                </strong>

                            </div>


                            <div class="profile-row">

                                <span>Warehouse</span>

                                <strong>
                                    <?= htmlspecialchars(
                                        $profile['warehouse'] ?? '-'
                                    ); ?>
                                </strong>

                            </div>


                            <div class="profile-row">

                                <span>Status</span>

                                <span class="badge bg-success">

                                    <?= htmlspecialchars(
                                        $profile['status'] ?? 'Active'
                                    ); ?>

                                </span>

                            </div>


                        </div>

                    </div>

                </div>

            </div>



            <!-- RIGHT DETAILS -->

            <div class="col-lg-8">

                <div class="card border-0 shadow-sm">


                    <div class="card-header bg-white py-3">

                        <h5 class="fw-bold mb-0">

                            <i class="fa-solid fa-id-card me-2"></i>

                            Employee Information

                        </h5>

                    </div>


                    <div class="card-body p-4">


                        <div class="row g-4">


                            <!-- FULL NAME -->

                            <div class="col-md-6">

                                <label class="form-label">

                                    Full Name

                                </label>

                                <input

                                    type="text"

                                    class="form-control"

                                    value="<?= htmlspecialchars(
                                        $profile['full_name'] ?? ''
                                    ); ?>"

                                    readonly

                                >

                            </div>



                            <!-- EMPLOYEE ID -->

                            <div class="col-md-6">

                                <label class="form-label">

                                    Employee ID

                                </label>

                                <input

                                    type="text"

                                    class="form-control"

                                    value="<?= htmlspecialchars(
                                        $profile['employee_id'] ?? ''
                                    ); ?>"

                                    readonly

                                >

                            </div>



                            <!-- MOBILE -->

                            <div class="col-md-6">

                                <label class="form-label">

                                    Mobile

                                </label>

                                <input

                                    type="text"

                                    class="form-control"

                                    value="<?= htmlspecialchars(
                                        $profile['mobile'] ?? ''
                                    ); ?>"

                                    readonly

                                >

                            </div>



                            <!-- EMAIL -->

                            <div class="col-md-6">

                                <label class="form-label">

                                    Email

                                </label>

                                <input

                                    type="email"

                                    class="form-control"

                                    value="<?= htmlspecialchars(
                                        $profile['email'] ?? ''
                                    ); ?>"

                                    readonly

                                >

                            </div>



                            <!-- GENDER -->

                            <div class="col-md-6">

                                <label class="form-label">

                                    Gender

                                </label>

                                <input

                                    type="text"

                                    class="form-control"

                                    value="<?= htmlspecialchars(
                                        $profile['gender'] ?? ''
                                    ); ?>"

                                    readonly

                                >

                            </div>



                            <!-- DOB -->

                            <div class="col-md-6">

                                <label class="form-label">

                                    Date of Birth

                                </label>

                                <input

                                    type="text"

                                    class="form-control"

                                    value="<?= htmlspecialchars(
                                        $profile['dob'] ?? ''
                                    ); ?>"

                                    readonly

                                >

                            </div>



                            <!-- DEPARTMENT -->

                            <div class="col-md-6">

                                <label class="form-label">

                                    Department

                                </label>

                                <input

                                    type="text"

                                    class="form-control"

                                    value="<?= htmlspecialchars(
                                        $profile['department'] ?? ''
                                    ); ?>"

                                    readonly

                                >

                            </div>



                            <!-- DESIGNATION -->

                            <div class="col-md-6">

                                <label class="form-label">

                                    Designation

                                </label>

                                <input

                                    type="text"

                                    class="form-control"

                                    value="<?= htmlspecialchars(
                                        $profile['designation'] ?? ''
                                    ); ?>"

                                    readonly

                                >

                            </div>



                            <!-- ROLE -->

                            <div class="col-md-6">

                                <label class="form-label">

                                    Role

                                </label>

                                <input

                                    type="text"

                                    class="form-control"

                                    value="<?= htmlspecialchars(
                                        $profile['role'] ?? ''
                                    ); ?>"

                                    readonly

                                >

                            </div>


                        </div>

                    </div>

                </div>

            </div>


        </div>

    </div>

</div>


<style>

.profile-main-image {

    width: 120px;

    height: 120px;

    object-fit: cover;

    border-radius: 50%;

    border: 4px solid #3b82f6;

    margin-bottom: 15px;

}

.profile-row {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    margin-bottom: 16px;

    font-size: 14px;

}

.profile-row > span:first-child {

    color: #6b7280;

}

.profile-row strong {

    color: #111827;

    text-align: right;

}

</style>


<?php include "../../includes/footer.php"; ?>