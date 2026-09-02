<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$role = $_SESSION['role'] ?? 'admin';
$adminName = $_SESSION['name'] ?? 'Admin';

$basePath = '/SanctuarioSystem/';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Farm Map | Sanctuario Nature Farm</title>

    <!-- POPPINS -->
    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <!-- FONT AWESOME -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <!-- SIDEBAR -->
    <link
        rel="stylesheet"
        href="<?= $basePath ?>sidebar.css"
    >

    <!-- FARM MAP CSS -->
    <link
        rel="stylesheet"
        href="<?= $basePath ?>farmMap/farmMap.css"
    >


<style>


</style>

</head>


<body>


<!-- =========================================================
     SIDEBAR
========================================================= -->

<?php include '../sidebar.php'; ?>


<!-- =========================================================
     MAIN CONTENT
========================================================= -->

<main class="farm-map-page">


    <!-- TOP RIGHT BAR -->

    <?php include '../topRightBar.php'; ?>


    <!-- =====================================================
         SUMMARY
    ====================================================== -->

    <section class="farm-summary">

        <div class="farm-map-title-area">

            <div>

                <h1>
                    FARM MAP
                </h1>

                <p>
                    View and monitor farm plots and planted crops.
                </p>

            </div>


            <!-- MAP EDIT AREA -->

            <div class="map-edit-toolbar-wrapper">

                <button
                    type="button"
                    id="editMapBtn"
                    class="map-edit-btn"
                >

                    <i class="fa-solid fa-pen-to-square"></i>

                    Edit Map

                </button>


                <div
                    id="mapEditControls"
                    class="map-edit-controls"
                >



                    <button
                        type="button"
                        data-tool="move"
                    >

                        <i class="fa-solid fa-up-down-left-right"></i>

                        Move

                    </button>


                    <button
                        type="button"
                        id="saveMapBtn"
                    >

                        <i class="fa-solid fa-floppy-disk"></i>

                        Save

                    </button>


                    <button
                        type="button"
                        id="cancelMapBtn"
                    >

                        <i class="fa-solid fa-xmark"></i>

                        Cancel

                    </button>

                </div>


                <span
                    id="mapEditStatus"
                    class="map-edit-status"
                >
                    View Mode
                </span>

            </div>

        </div>


        <!-- =================================================
             SUMMARY CARDS
        ================================================== -->

        <div class="summary-cards">

            <div class="summary-card">

                <div class="summary-icon summary-green">

                    <i class="fa-solid fa-seedling"></i>

                </div>

                <div>

                    <span class="summary-label">
                        Total Crops
                    </span>

                    <strong class="summary-number">
                        --
                    </strong>

                </div>

            </div>


            <div class="summary-card">

                <div class="summary-icon summary-yellow">

                    <i class="fa-solid fa-cow"></i>

                </div>

                <div>

                    <span class="summary-label">
                        Total Livestocks
                    </span>

                    <strong class="summary-number">
                        --
                    </strong>

                </div>

            </div>


            <div class="summary-card">

                <div class="summary-icon summary-light-green">

                    <i class="fa-solid fa-check"></i>

                </div>

                <div>

                    <span class="summary-label">
                        Harvest Ready
                    </span>

                    <strong class="summary-number">
                        --
                    </strong>

                </div>

            </div>


        </div>

    </section>


    <!-- =====================================================
         MAP
    ====================================================== -->

    <section class="farm-map-layout">


        <div class="map-card">

            <div
                class="map-container"
                id="mapContainer"
            >

                <div
                    class="main-map-layer"
                    id="mainMapLayer"
                >

                    <img
                        src="<?= $basePath ?>farm_map.jpg"
                        id="farmMap"
                        class="farm-map-image"
                        draggable="false"
                        alt="Sanctuario Nature Farm"
                    >


                    <!-- =================================================
                         PLOT A
                    ================================================== -->

                    <button
                        type="button"
                        class="main-marker plot-marker editable-marker"
                        id="plot-A"
                        data-marker="plot"
                        data-id="A"
                        data-name="Plot A"
                        style="left:82.2%; top:53.4%;"
                    >

                        <img
                            src="<?= $basePath ?>markers/plot_a.png"
                            alt="Plot A"
                        >

                        <span>
                            Plot A
                        </span>

                    </button>


                    <!-- =================================================
                         PLOT B
                    ================================================== -->

                    <button
                        type="button"
                        class="main-marker plot-marker editable-marker"
                        id="plot-B"
                        data-marker="plot"
                        data-id="B"
                        data-name="Plot B"
                        style="left:72.4%; top:52%;"
                    >

                        <img
                            src="<?= $basePath ?>markers/plot_b.png"
                            alt="Plot B"
                        >

                        <span>
                            Plot B
                        </span>

                    </button>


                    <!-- =================================================
                         PLOT C
                    ================================================== -->

                    <button
                        type="button"
                        class="main-marker plot-marker editable-marker"
                        id="plot-C"
                        data-marker="plot"
                        data-id="C"
                        data-name="Plot C"
                        style="left:63.3%; top:52.8%;"
                    >

                        <img
                            src="<?= $basePath ?>markers/plot_c.png"
                            alt="Plot C"
                        >

                        <span>
                            Plot C
                        </span>

                    </button>


                    <!-- =================================================
                         NURSERY
                    ================================================== -->

                    <button
                        type="button"
                        class="main-marker nursery-marker editable-marker"
                        id="marker-nursery"
                        data-marker="nursery"
                        data-id="nursery"
                        data-name="Nursery"
                        style="left:85.4%; top:15.8%;"
                    >

                        <img
                            src="<?= $basePath ?>markers/nursery.png"
                            alt="Nursery"
                        >

                        <span>
                            Nursery
                        </span>

                    </button>


                    <!-- =================================================
                         RABBIT HOUSE
                    ================================================== -->

                    <button
                        type="button"
                        class="main-marker livestock-marker editable-marker"
                        id="marker-rabbit-house"
                        data-marker="livestock"
                        data-id="rabbit-house"
                        data-name="Rabbit House"
                        style="left:23.7%; top:7%;"
                    >

                        <img
                            src="<?= $basePath ?>markers/rabbit_house.png"
                            alt="Rabbit House"
                        >

                        <span>
                            Rabbit House
                        </span>

                    </button>


                    <!-- =================================================
                         GOAT HOUSE
                    ================================================== -->

                    <button
                        type="button"
                        class="main-marker livestock-marker editable-marker"
                        id="marker-goat-house"
                        data-marker="livestock"
                        data-id="goat-house"
                        data-name="Goat House"
                        style="left:15%; top:16%;"
                    >

                        <img
                            src="<?= $basePath ?>markers/goat_house.png"
                            alt="Goat House"
                        >

                        <span>
                            Goat House
                        </span>

                    </button>


                    <!-- =================================================
                         GOAT HOUSE 2
                    ================================================== -->

                    <button
                        type="button"
                        class="main-marker livestock-marker editable-marker"
                        id="marker-goat-house-2"
                        data-marker="livestock"
                        data-id="goat-house-2"
                        data-name="Goat House 2"
                        style="left:9.1%; top:8%;"
                    >

                        <img
                            src="<?= $basePath ?>markers/goat_house.png"
                            alt="Goat House 2"
                        >

                        <span>
                            Goat House 2
                        </span>

                    </button>


                    <!-- =================================================
                         POULTRY HOUSE
                    ================================================== -->

                    <button
                        type="button"
                        class="main-marker livestock-marker editable-marker"
                        id="marker-poultry-house"
                        data-marker="livestock"
                        data-id="poultry-house"
                        data-name="Poultry House"
                        style="left:6.5%; top:19.5%;"
                    >

                        <img
                            src="<?= $basePath ?>markers/poultry_house.png"
                            alt="Poultry House"
                        >

                        <span>
                            Poultry House
                        </span>

                    </button>


                    <!-- =================================================
                         SWINE HOUSE
                    ================================================== -->

                    <button
                        type="button"
                        class="main-marker livestock-marker editable-marker"
                        id="marker-swine-house"
                        data-marker="livestock"
                        data-id="swine-house"
                        data-name="Swine House"
                        style="left:12%; top:74.6%;"
                    >

                        <img
                            src="<?= $basePath ?>markers/swine_house.png"
                            alt="Swine House"
                        >

                        <span>
                            Swine House
                        </span>

                    </button>

                </div>


                <!-- =================================================
                     MAP HINT
                ================================================== -->

                <div
                    class="map-hint"
                    id="mapHint"
                >
                    Drag the map to view other areas • Click a plot to zoom
                </div>


                <!-- =================================================
                     PLOT POPUP
                ================================================== -->

                <div
                    class="plot-overlay"
                    id="plotOverlay"
                >

                    <div
                        class="plot-popup"
                        id="plotPopup"
                    >

                        <button
                            type="button"
                            class="popup-close"
                            id="closePlotBtn"
                        >

                            <i class="fa-solid fa-xmark"></i>

                        </button>


                        <div class="popup-map">

                            <img
                                id="plotImage"
                                src=""
                                draggable="false"
                                alt="Plot"
                            >

                            <div
                                id="bedMarkersContainer"
                            ></div>

                        </div>


                        <div class="plot-info">

                            <div class="plot-title-row">

                                <h3 id="plotTitle">
                                    Plot A
                                </h3>

                            </div>

                            <p id="plotDescription">
                                Main Vegetable Production Area
                            </p>

                              <!-- =================================================
                                 BED EDIT CONTROLS
                            ================================================== -->

                            <div
                                class="bed-edit-toolbar"
                                id="bedEditToolbar"
                            >

                                <button
                                    type="button"
                                    id="startEditBedsBtn"
                                >

                                    <i class="fa-solid fa-pen"></i>

                                    Edit Beds

                                </button>


                                <button
                                    type="button"
                                    id="addBedBtn"
                                    style="display:none;"
                                >

                                    <i class="fa-solid fa-plus"></i>

                                    Add Bed

                                </button>


                                <button
                                    type="button"
                                    id="deleteBedBtn"
                                    class="delete-bed"
                                    style="display:none;"
                                >

                                    <i class="fa-solid fa-trash"></i>

                                    Delete Selected

                                </button>


                                <button
                                    type="button"
                                    id="saveBedsBtn"
                                    style="display:none;"
                                >

                                    <i class="fa-solid fa-floppy-disk"></i>

                                    Save Beds

                                </button>


                                <button
                                    type="button"
                                    id="cancelBedsBtn"
                                    class="cancel-bed-edit"
                                    style="display:none;"
                                >

                                    <i class="fa-solid fa-xmark"></i>

                                    Cancel

                                </button>



                            </div>

                        </div>

                    </div>

                </div>

                            </div>

                          


                <!-- =================================================
                     NURSERY POPUP
                ================================================== -->

                <div
                    class="nursery-overlay"
                    id="nurseryOverlay"
                >

                    <div class="nursery-popup">

                        <button
                            type="button"
                            class="popup-close"
                            id="closeNurseryBtn"
                        >

                            <i class="fa-solid fa-xmark"></i>

                        </button>


                        <h3>

                            <i class="fa-solid fa-seedling"></i>

                            Nursery

                        </h3>

                        <p>
                            Seedlings and nursery crop timeline
                        </p>


                        <div class="nursery-gantt">

                            <div class="nursery-row">

                                <span>
                                    Lettuce
                                </span>

                                <div class="nursery-track">

                                    <div
                                        class="nursery-bar sowing"
                                        style="left:0%; width:35%;"
                                    ></div>

                                    <div
                                        class="nursery-bar transplant"
                                        style="left:35%; width:8%;"
                                    ></div>

                                    <div
                                        class="nursery-bar harvest"
                                        style="left:43%; width:35%;"
                                    ></div>

                                </div>

                            </div>


                            <div class="nursery-row">

                                <span>
                                    Pechay
                                </span>

                                <div class="nursery-track">

                                    <div
                                        class="nursery-bar sowing"
                                        style="left:5%; width:32%;"
                                    ></div>

                                    <div
                                        class="nursery-bar transplant"
                                        style="left:37%; width:8%;"
                                    ></div>

                                    <div
                                        class="nursery-bar harvest"
                                        style="left:45%; width:30%;"
                                    ></div>

                                </div>

                            </div>


                            <div class="nursery-row">

                                <span>
                                    Okra
                                </span>

                                <div class="nursery-track">

                                    <div
                                        class="nursery-bar sowing"
                                        style="left:10%; width:35%;"
                                    ></div>

                                    <div
                                        class="nursery-bar transplant"
                                        style="left:45%; width:8%;"
                                    ></div>

                                    <div
                                        class="nursery-bar harvest"
                                        style="left:53%; width:30%;"
                                    ></div>

                                </div>

                            </div>

                        </div>


                        <div class="nursery-legend">

                            <span>
                                <i class="sowing"></i>
                                Sowing
                            </span>

                            <span>
                                <i class="transplant"></i>
                                Transplant
                            </span>

                            <span>
                                <i class="harvest"></i>
                                Harvest
                            </span>

                        </div>

                    </div>

                </div>




                <!-- =================================================
                     LIVESTOCK HOUSE POPUP
                ================================================== -->

                <div class="livestock-overlay" id="livestockOverlay">

                    <div class="livestock-popup" id="livestockPopup">

                        <button type="button" class="popup-close" id="closeLivestockBtn">
                            <i class="fa-solid fa-xmark"></i>
                        </button>

                        <div class="livestock-house-map">

                            <img id="livestockHouseImage" src="" alt="Livestock House" draggable="false">

                            <div id="livestockMarkersContainer"></div>

                        </div>

                        <div class="livestock-house-info">
                            <h2 id="livestockHouseTitle">Goat House</h2>
                            <p id="livestockHouseDescription">Goat housing area.</p>
                            <span class="livestock-count" id="livestockCount">0 animals</span>
                        </div>

                    </div>

                </div>

                <!-- =================================================
                     LIVESTOCK PROFILE POPUP
                ================================================== -->

                <div class="livestock-profile-overlay" id="livestockProfileOverlay">

                    <div class="livestock-profile-popup" id="livestockProfilePopup">

                        <div class="livestock-profile-title">
                            <h2>
                                <i class="fa-solid fa-cow"></i>
                                <span id="profileLivestockName">GINO</span>
                            </h2>
                            <p id="profileLivestockType">Buck</p>

                            <button type="button" class="livestock-profile-close" id="closeLivestockProfileBtn">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>

                        <div class="livestock-profile-image-section">
                            <div>
                                <div class="livestock-profile-image-wrapper">
                                    <img id="livestockProfileImage" class="livestock-profile-image" src="" alt="Livestock" style="display:none;">

                                    <div class="livestock-image-placeholder" id="livestockImagePlaceholder">
                                        <i class="fa-solid fa-cow"></i>
                                        <span>No livestock picture uploaded</span>
                                    </div>
                                </div>

                                <div class="livestock-image-actions">
                                    <button type="button" class="livestock-image-btn" id="uploadLivestockImageBtn">
                                        <i class="fa-solid fa-camera"></i>
                                        Upload / Change Picture
                                    </button>

                                    <button type="button" class="livestock-image-btn remove" id="removeLivestockImageBtn">
                                        <i class="fa-solid fa-trash"></i>
                                        Remove
                                    </button>

                                    <input type="file" id="livestockImageInput" accept="image/*" hidden>
                                </div>
                            </div>
                        </div>

                        <div class="livestock-profile-content">

                            <div class="livestock-profile-grid">

                                <div class="livestock-profile-field">
                                    <span>Date of Birth</span>
                                    <strong id="profileLivestockDOB">December</strong>
                                </div>

                                <div class="livestock-profile-field">
                                    <span>Breed</span>
                                    <strong id="profileLivestockBreed">Native</strong>
                                </div>

                                <div class="livestock-profile-field">
                                    <span>Age</span>
                                    <strong id="profileLivestockAge">17 months</strong>
                                </div>

                                <div class="livestock-profile-field">
                                    <span>Estimated Weight</span>
                                    <strong id="profileLivestockWeight">25 kg</strong>
                                </div>

                                <div class="livestock-profile-field full">
                                    <span>Notes</span>
                                    <strong id="profileLivestockNotes">Happy goat</strong>
                                </div>

                            </div>

                            <div class="livestock-edit-form" id="livestockEditForm">

                                <div class="livestock-edit-grid">

                                    <div class="livestock-input-group">
                                        <label>Animal Name</label>
                                        <input type="text" id="editLivestockName">
                                    </div>

                                    <div class="livestock-input-group">
                                        <label>Sex / Type</label>
                                        <select id="editLivestockSex">
                                            <option value="Buck">Buck</option>
                                            <option value="Doe">Doe</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                        </select>
                                    </div>

                                    <div class="livestock-input-group">
                                        <label>Date of Birth</label>
                                        <input type="date" id="editLivestockDOB">
                                    </div>

                                    <div class="livestock-input-group">
                                        <label>Breed</label>
                                        <input type="text" id="editLivestockBreed">
                                    </div>

                                    <div class="livestock-input-group">
                                        <label>Estimated Weight</label>
                                        <input type="text" id="editLivestockWeight">
                                    </div>

                                    <div class="livestock-input-group full">
                                        <label>Notes</label>
                                        <textarea id="editLivestockNotes"></textarea>
                                    </div>

                                </div>

                            </div>

                            <div class="livestock-profile-actions">
                                <button type="button" class="livestock-edit-btn" id="editLivestockProfileBtn">
                                    <i class="fa-solid fa-pen"></i>
                                    Edit Profile
                                </button>

                                <button type="button" class="livestock-save-btn" id="saveLivestockProfileBtn" style="display:none;">
                                    <i class="fa-solid fa-floppy-disk"></i>
                                    Save Profile
                                </button>

                                <button type="button" class="livestock-cancel-btn" id="cancelLivestockProfileBtn" style="display:none;">
                                    <i class="fa-solid fa-xmark"></i>
                                    Cancel
                                </button>
                            </div>

                        </div>

                    </div>

                </div>

                <!-- =================================================
                     CROP PROFILE POPUP
                ================================================== -->

                <div
                    class="crop-profile-overlay"
                    id="cropProfileOverlay"
                >

                    <div
                        class="crop-profile-popup"
                        id="cropProfilePopup"
                    >

                        <!-- HEADER -->

                        <div class="crop-profile-title">

                            <div>

                                <h2>
                                    <i class="fa-solid fa-seedling"></i>
                                    LETTUCE
                                </h2>

                                <p id="profileCropVariety">
                                     Majesta Variety
                                </p>

                                <button
                                type="button"
                                class="crop-profile-close"
                                id="closeCropProfileBtn"
                            >

                                <i class="fa-solid fa-xmark"></i>

                            </button>

                            </div>


                            

                        </div>


                        <!-- IMAGE -->

                        <div class="crop-profile-image-section">

                            <div>

                                <div
                                    class="crop-profile-image-wrapper"
                                    id="cropImageWrapper"
                                >

                                    <img
                                        id="cropProfileImage"
                                        class="crop-profile-image"
                                        src=""
                                        alt="Crop"
                                        style="display:none;"
                                    >


                                    <div
                                        class="crop-image-placeholder"
                                        id="cropImagePlaceholder"
                                    >

                                        <i class="fa-solid fa-seedling"></i>

                                        <span>
                                            No crop picture uploaded
                                        </span>

                                    </div>

                                </div>


                                <div
                                    class="crop-image-actions"
                                    id="cropImageActions"
                                >

                                    <button
                                        type="button"
                                        class="crop-image-btn"
                                        id="uploadCropImageBtn"
                                    >

                                        <i class="fa-solid fa-camera"></i>

                                        Upload / Change Picture

                                    </button>


                                    <button
                                        type="button"
                                        class="crop-image-btn remove"
                                        id="removeCropImageBtn"
                                    >

                                        <i class="fa-solid fa-trash"></i>

                                        Remove

                                    </button>


                                    <input
                                        type="file"
                                        id="cropImageInput"
                                        accept="image/*"
                                        hidden
                                    >

                                </div>

                            </div>

                        </div>


                        <!-- PROFILE CONTENT -->

                        <div class="crop-profile-content">


                            <!-- BASIC DETAILS -->

                            <div class="crop-profile-grid">

                                <div class="crop-profile-field">

                                    <span>
                                        Staff
                                    </span>

                                    <strong id="profileCropStaff">
                                        Mr. Villa
                                    </strong>

                                </div>


                                <div class="crop-profile-field">

                                    <span>
                                        Quantity
                                    </span>

                                    <strong id="profileCropPCS">
                                        5 Trays
                                    </strong>

                                </div>


                                <div class="crop-profile-field">

                                    <span>
                                        Germination Rate
                                    </span>

                                    <strong id="profileCropGermination">
                                        79%
                                    </strong>

                                </div>


                                <div class="crop-profile-field">

                                    <span>
                                        Targeted Area
                                    </span>

                                    <strong id="profileCropArea">
                                        A1
                                    </strong>

                                </div>


                                <div class="crop-profile-field">

                                    <span>
                                        Planted Date
                                    </span>

                                    <strong id="profileCropPlantedDate">
                                        --
                                    </strong>

                                </div>


                                <div class="crop-profile-field">

                                    <span>
                                        Status
                                    </span>

                                    <strong>
                                        <span
                                            class="crop-status"
                                            id="profileCropStatus"
                                        >
                                            Growing
                                        </span>
                                    </strong>

                                </div>

                            </div>


                            <!-- =================================================
                                 CROP TIMELINE
                            ================================================== -->

                            <div class="crop-timeline-box">

                                <div class="crop-timeline-title">

                                    <strong>
                                        Crop Growth Timeline
                                    </strong>

                                    <span id="cropTimelineText">
                                        0 days planted
                                    </span>

                                </div>


                                <div class="crop-progress-track">

                                    <div
                                        class="crop-progress-bar"
                                        id="cropProgressBar"
                                    ></div>

                                </div>


                                <div class="crop-timeline-stats">

                                    <div class="crop-time-stat">

                                        <span>
                                            Days Planted
                                        </span>

                                        <strong id="profileDaysPlanted">
                                            0 days
                                        </strong>

                                    </div>


                                    <div class="crop-time-stat">

                                        <span>
                                            Weeks Planted
                                        </span>

                                        <strong id="profileWeeksPlanted">
                                            0 weeks
                                        </strong>

                                    </div>


                                    <div class="crop-time-stat">

                                        <span>
                                            Expected Harvest
                                        </span>

                                        <strong id="profileCropHarvest">
                                            --
                                        </strong>

                                    </div>

                                </div>

                            </div>


                            <!-- =================================================
                                 EDIT FORM
                            ================================================== -->

                            <div
                                class="crop-edit-form"
                                id="cropEditForm"
                            >

                                <div class="crop-edit-grid">


                                    <div class="crop-input-group">

                                        <label>
                                            Crop Name
                                        </label>

                                        <input
                                            type="text"
                                            id="editCropName"
                                        >

                                    </div>


                                    <div class="crop-input-group">

                                        <label>
                                            Variety
                                        </label>

                                        <input
                                            type="text"
                                            id="editCropVariety"
                                        >

                                    </div>


                                    <div class="crop-input-group">

                                        <label>
                                            Staff
                                        </label>

                                        <input
                                            type="text"
                                            id="editCropStaff"
                                        >

                                    </div>


                                    <div class="crop-input-group">

                                        <label>
                                            Quantity / PCS
                                        </label>

                                        <input
                                            type="text"
                                            id="editCropPCS"
                                        >

                                    </div>


                                    <div class="crop-input-group">

                                        <label>
                                            Germination Rate
                                        </label>

                                        <input
                                            type="text"
                                            id="editCropGermination"
                                        >

                                    </div>


                                    <div class="crop-input-group">

                                        <label>
                                            Targeted Area
                                        </label>

                                        <input
                                            type="text"
                                            id="editCropArea"
                                        >

                                    </div>


                                    <div class="crop-input-group">

                                        <label>
                                            Planted Date
                                        </label>

                                        <input
                                            type="date"
                                            id="editCropPlantedDate"
                                        >

                                    </div>


                                    <div class="crop-input-group">

                                        <label>
                                            Weeks Planted
                                        </label>

                                        <input
                                            type="number"
                                            id="editCropWeeks"
                                            min="0"
                                            step="1"
                                        >

                                    </div>


                                    <div class="crop-input-group">

                                        <label>
                                            Expected Harvest
                                        </label>

                                        <input
                                            type="date"
                                            id="editCropHarvest"
                                        >

                                    </div>


                                    <div class="crop-input-group">

                                        <label>
                                            Crop Status
                                        </label>

                                        <select
                                            id="editCropStatus"
                                        >

                                            <option value="Planted">
                                                Planted
                                            </option>

                                            <option value="Growing">
                                                Growing
                                            </option>

                                            <option value="Ready for Harvest">
                                                Ready for Harvest
                                            </option>

                                            <option value="Harvested">
                                                Harvested
                                            </option>

                                            <option value="Failed">
                                                Failed
                                            </option>

                                        </select>

                                    </div>

                                </div>

                            </div>


                            <!-- ACTIONS -->

                            <div class="crop-profile-actions">

                                <button
                                    type="button"
                                    class="crop-edit-btn"
                                    id="editCropProfileBtn"
                                >

                                    <i class="fa-solid fa-pen"></i>

                                    Edit Profile

                                </button>


                                <button
                                    type="button"
                                    class="crop-save-btn"
                                    id="saveCropProfileBtn"
                                    style="display:none;"
                                >

                                    <i class="fa-solid fa-floppy-disk"></i>

                                    Save Profile

                                </button>


                                <button
                                    type="button"
                                    class="crop-cancel-btn"
                                    id="cancelCropProfileBtn"
                                    style="display:none;"
                                >

                                    <i class="fa-solid fa-xmark"></i>

                                    Cancel

                                </button>

                            </div>

                        </div>

                    </div>

                </div>


            </div>

        </div>


        <!-- =====================================================
             RIGHT SIDE
        ====================================================== -->

        <aside class="farm-side-panel">


            <!-- UPCOMING HARVEST -->

            <div class="farm-panel">

                <div class="panel-header">

                    <span class="panel-title">
                        UPCOMING HARVEST
                    </span>

                    <select
                        class="plot-select"
                        id="plotSelect"
                    >

                        <option value="A">
                            Plot A
                        </option>

                        <option value="B">
                            Plot B
                        </option>

                        <option value="C">
                            Plot C
                        </option>

                    </select>

                </div>


                <div class="harvest-item">

                    <strong>
                        Lettuce
                    </strong>

                    <span>
                        June 10, 2026 - Plot A1
                    </span>

                </div>


                <div class="harvest-item">

                    <strong>
                        Corn
                    </strong>

                    <span>
                        June 10, 2026 - Plot A2
                    </span>

                </div>


                <div class="harvest-item">

                    <strong>
                        Pechay
                    </strong>

                    <span>
                        June 12, 2026 - Plot A3
                    </span>

                </div>


                <div class="harvest-item">

                    <strong>
                        Eggplant
                    </strong>

                    <span>
                        June 14, 2026 - Plot A4
                    </span>

                </div>

            </div>


            <!-- PLOT INFORMATION -->

        </aside>

    </section>

</main>

<script>

/* =========================================================
   SANCTUARIO FARM MAP
   CROP PROFILES + BED EDITING + MAP EDITING
========================================================= */


/* =========================================================
   BASIC MAP REFERENCES
========================================================= */

const mapContainer =
    document.getElementById("mapContainer");

const mainMapLayer =
    document.getElementById("mainMapLayer");

const farmMap =
    document.getElementById("farmMap");


/* =========================================================
   MAP PAN / DRAG
========================================================= */

let translateX = 0;

let draggingMap = false;

let mapStartX = 0;

const mapScale = 1;


function getMapWidth() {

    return farmMap.offsetWidth ||
           mainMapLayer.scrollWidth ||
           0;

}


function clampMap() {

    const containerWidth =
        mapContainer.clientWidth;

    const mapWidth =
        getMapWidth() * mapScale;


    if (mapWidth <= containerWidth) {

        translateX =
            (containerWidth - mapWidth) / 2;

        return;

    }


    const minX =
        containerWidth - mapWidth;


    translateX =
        Math.max(
            minX,
            Math.min(0, translateX)
        );

}


function updateMainMap() {

    mainMapLayer.style.transform =
        `translate3d(${translateX}px, 0, 0) scale(${mapScale})`;

}


function initializeMap() {

    translateX = 0;

    clampMap();

    updateMainMap();

}


if (farmMap.complete) {

    requestAnimationFrame(
        initializeMap
    );

} else {

    farmMap.addEventListener(
        "load",
        () => requestAnimationFrame(initializeMap),
        { once: true }
    );

}


function isOverlayTarget(target) {

    return !!target.closest(
        ".plot-overlay, .plant-overlay, .nursery-overlay, .crop-profile-overlay, .livestock-overlay, .livestock-profile-overlay"
    );

}


mapContainer.addEventListener(
    "mousedown",
    function(event) {

        if (
            event.target.closest(".main-marker") ||
            isOverlayTarget(event.target)
        ) {
            return;
        }


        if (
            mapEditMode &&
            mapEditTool !== "move"
        ) {
            return;
        }


        draggingMap = true;

        mapStartX = event.clientX;

        mapContainer.classList.add(
            "dragging"
        );

        event.preventDefault();

    }
);


document.addEventListener(
    "mousemove",
    function(event) {

        if (!draggingMap) return;


        const dx =
            event.clientX - mapStartX;

        mapStartX =
            event.clientX;


        translateX += dx;

        clampMap();

        updateMainMap();

    }
);


document.addEventListener(
    "mouseup",
    function() {

        draggingMap = false;

        mapContainer.classList.remove(
            "dragging"
        );

    }
);


farmMap.addEventListener(
    "dragstart",
    event => event.preventDefault()
);


/* =========================================================
   TOUCH MAP DRAG
========================================================= */

let touchStartX = 0;

let touchingMap = false;


mapContainer.addEventListener(
    "touchstart",
    function(event) {

        if (
            event.target.closest(".main-marker") ||
            isOverlayTarget(event.target)
        ) {
            return;
        }


        if (
            mapEditMode &&
            mapEditTool !== "move"
        ) {
            return;
        }


        touchingMap = true;

        touchStartX =
            event.touches[0].clientX;

    },
    {
        passive: true
    }
);


mapContainer.addEventListener(
    "touchmove",
    function(event) {

        if (!touchingMap) return;


        const currentX =
            event.touches[0].clientX;


        translateX +=
            currentX - touchStartX;


        touchStartX =
            currentX;


        clampMap();

        updateMainMap();

    },
    {
        passive: true
    }
);


mapContainer.addEventListener(
    "touchend",
    function() {

        touchingMap = false;

    }
);


window.addEventListener(
    "resize",
    function() {

        clampMap();

        updateMainMap();

    }
);


/* =========================================================
   PLOT DATA
========================================================= */

const plotData = {

    A: {

        image:
            "<?= $basePath ?>farmMap/plot_a.jpg?v=2",

        title:
            "Plot A",

        description:
            "Main Vegetable Production Area",

        beds:
            18,

        area:
            "500m²"

    },


    B: {

        image:
            "<?= $basePath ?>farmMap/plot_b.jpg?v=3",

        title:
            "Plot B",

        description:
            "Secondary Vegetable Production Area",

        beds:
            12,

        area:
            "400m²"

    },


    C: {

        image:
            "<?= $basePath ?>farmMap/plot_c.jpg?v=2",

        title:
            "Plot C",

        description:
            "Crop Production Area",

        beds:
            15,

        area:
            "300m²"

    }

};


/* =========================================================
   BED STORAGE
========================================================= */

const BED_STORAGE_KEY =
    "sanctuarioPlotBeds";


let currentPlot = null;

let bedEditMode = false;

let selectedBed = null;

let draggingBed = null;

let bedSnapshot = null;


function getStoredBeds() {

    try {

        const value =
            JSON.parse(
                localStorage.getItem(
                    BED_STORAGE_KEY
                )
            );


        return value &&
               typeof value === "object" &&
               !Array.isArray(value)
            ? value
            : {};

    } catch (error) {

        console.error(
            "Unable to load bed positions:",
            error
        );

        return {};

    }

}


function saveStoredBeds(data) {

    localStorage.setItem(
        BED_STORAGE_KEY,
        JSON.stringify(data)
    );

}


/* =========================================================
   DEFAULT BED POSITIONS
========================================================= */

function createDefaultBedPositions(count) {

    const positions = [];

    count =
        Math.max(
            0,
            Number(count) || 0
        );


    if (!count) return positions;


    const columns =
        Math.ceil(
            Math.sqrt(count)
        );


    const rows =
        Math.ceil(
            count / columns
        );


    for (
        let i = 0;
        i < count;
        i++
    ) {

        const row =
            Math.floor(
                i / columns
            );


        const column =
            i % columns;


        const x =
            columns === 1
                ? 50
                : 15 +
                  column *
                  (70 / (columns - 1));


        const y =
            rows === 1
                ? 50
                : 15 +
                  row *
                  (70 / (rows - 1));


        positions.push({

            number:
                i + 1,

            x:
                Math.min(85, x),

            y:
                Math.min(85, y)

        });

    }


    return positions;

}


function getBedsForPlot(plot) {

    const stored =
        getStoredBeds();


    if (
        Array.isArray(
            stored[plot]
        )
    ) {

        return stored[plot];

    }


    return plotData[plot]
        ? createDefaultBedPositions(
            plotData[plot].beds
        )
        : [];

}


/* =========================================================
   CREATE BED MARKER
========================================================= */

function createBedMarker(bed) {

    const marker =
        document.createElement("button");


    marker.type = "button";

    marker.className =
        "bed-marker";


    marker.dataset.bed =
        String(bed.number);


    marker.textContent =
        bed.number;


    marker.style.left =
        `${Number(bed.x)}%`;


    marker.style.top =
        `${Number(bed.y)}%`;


    marker.title =
        `Bed ${bed.number}`;


    marker.addEventListener(
        "click",
        function(event) {

            event.stopPropagation();


            if (bedEditMode) {

                selectBed(marker);

                return;

            }


            openCropProfile(
                Number(
                    marker.dataset.bed
                )
            );

        }
    );


    marker.addEventListener(
        "mousedown",
        function(event) {

            if (!bedEditMode) return;


            event.preventDefault();

            event.stopPropagation();


            draggingBed =
                marker;


            selectBed(marker);

        }
    );


    return marker;

}


/* =========================================================
   RENDER BEDS
========================================================= */

function renderBedMarkers(plot) {

    const container =
        document.getElementById(
            "bedMarkersContainer"
        );


    container.innerHTML = "";


    getBedsForPlot(plot)
        .forEach(
            bed => {

                container.appendChild(
                    createBedMarker(bed)
                );

            }
        );


    updateBedCount();

}


/* =========================================================
   BED COUNT
========================================================= */

function updateBedCount() {

    const count =
        document.querySelectorAll(
            "#bedMarkersContainer .bed-marker"
        ).length;


    const sideBeds =
        document.getElementById(
            "sidePlotBeds"
        );


    if (sideBeds) {

        sideBeds.textContent =
            count;

    }

}


/* =========================================================
   COLLECT BED POSITIONS
========================================================= */

function collectCurrentBedPositions() {

    return [
        ...document.querySelectorAll(
            "#bedMarkersContainer .bed-marker"
        )
    ].map(marker => ({

        number:
            Number(
                marker.dataset.bed
            ),

        x:
            parseFloat(
                marker.style.left
            ) || 0,

        y:
            parseFloat(
                marker.style.top
            ) || 0

    }));

}


/* =========================================================
   OPEN PLOT
========================================================= */

function openPlot(plot) {

    const data =
        plotData[plot];


    if (!data) {

        alert(
            "This plot does not have plot information yet."
        );

        return;

    }


    currentPlot =
        plot;


    bedEditMode =
        false;


    selectedBed =
        null;


    draggingBed =
        null;


    bedSnapshot =
        null;


    document.getElementById(
        "plotPopup"
    ).classList.remove(
        "bed-editing"
    );


    document.getElementById(
        "plotImage"
    ).src =
        data.image;


    document.getElementById(
        "plotTitle"
    ).textContent =
        data.title;


    document.getElementById(
        "plotDescription"
    ).textContent =
        data.description;




    renderBedMarkers(plot);


    document.getElementById(
        "plotOverlay"
    ).classList.add(
        "show"
    );


    updateBedEditUI();

    updateSidePlotInfo(plot);

}


/* =========================================================
   CLOSE PLOT
========================================================= */

function closePlot() {

    if (bedEditMode) {

        const confirmed =
            confirm(
                "You are currently editing bed positions. Close without saving?"
            );


        if (!confirmed) return;


        restoreBedSnapshot();

    }


    bedEditMode =
        false;


    selectedBed =
        null;


    draggingBed =
        null;


    bedSnapshot =
        null;


    currentPlot =
        null;


    document.getElementById(
        "plotPopup"
    ).classList.remove(
        "bed-editing"
    );


    document.getElementById(
        "plotOverlay"
    ).classList.remove(
        "show"
    );

}


/* =========================================================
   BED SNAPSHOT
========================================================= */

function createBedSnapshot() {

    bedSnapshot =
        JSON.parse(
            JSON.stringify(
                collectCurrentBedPositions()
            )
        );

}


function restoreBedSnapshot() {

    if (
        !Array.isArray(
            bedSnapshot
        )
    ) {
        return;
    }


    const container =
        document.getElementById(
            "bedMarkersContainer"
        );


    container.innerHTML = "";


    bedSnapshot.forEach(
        bed => {

            container.appendChild(
                createBedMarker(bed)
            );

        }
    );


    updateBedCount();

}


/* =========================================================
   START BED EDITING
========================================================= */

function startBedEditing() {

    if (!currentPlot) return;


    createBedSnapshot();


    bedEditMode =
        true;


    selectedBed =
        null;


    document.getElementById(
        "plotPopup"
    ).classList.add(
        "bed-editing"
    );


    document.querySelectorAll(
        "#bedMarkersContainer .bed-marker"
    ).forEach(
        marker => {

            marker.classList.add(
                "editing"
            );

        }
    );


    updateBedEditUI();

}


/* =========================================================
   BED EDIT UI
========================================================= */

function updateBedEditUI() {

    const editButton =
        document.getElementById(
            "startEditBedsBtn"
        );


    const addButton =
        document.getElementById(
            "addBedBtn"
        );


    const deleteButton =
        document.getElementById(
            "deleteBedBtn"
        );


    const saveButton =
        document.getElementById(
            "saveBedsBtn"
        );


    const cancelButton =
        document.getElementById(
            "cancelBedsBtn"
        );


    if (!bedEditMode) {

        editButton.style.display =
            "inline-flex";


        addButton.style.display =
            "none";


        deleteButton.style.display =
            "none";


        saveButton.style.display =
            "none";


        cancelButton.style.display =
            "none";


        status.textContent =
            "Click a bed marker to view its crop profile.";


        return;

    }


    editButton.style.display =
        "none";


    addButton.style.display =
        "inline-flex";


    deleteButton.style.display =
        "inline-flex";


    saveButton.style.display =
        "inline-flex";


    cancelButton.style.display =
        "inline-flex";


    status.textContent =
        selectedBed
            ? `Bed ${selectedBed.dataset.bed} selected — drag it to move.`
            : "Drag a bed marker or select one.";

}


/* =========================================================
   SELECT BED
========================================================= */

function selectBed(marker) {

    document.querySelectorAll(
        "#bedMarkersContainer .bed-marker"
    ).forEach(
        item => {

            item.classList.remove(
                "selected"
            );

        }
    );


    selectedBed =
        marker;


    marker.classList.add(
        "selected"
    );


    updateBedEditUI();

}


/* =========================================================
   DRAG BED
========================================================= */

document.addEventListener(
    "mousemove",
    function(event) {

        if (
            !draggingBed ||
            !bedEditMode
        ) {
            return;
        }


        const popupMap =
            document.querySelector(
                ".popup-map"
            );


        if (!popupMap) return;


        const rect =
            popupMap.getBoundingClientRect();


        const x =
            Math.max(
                2,
                Math.min(
                    98,
                    (
                        (event.clientX - rect.left) /
                        rect.width
                    ) * 100
                )
            );


        const y =
            Math.max(
                2,
                Math.min(
                    98,
                    (
                        (event.clientY - rect.top) /
                        rect.height
                    ) * 100
                )
            );


        draggingBed.style.left =
            `${x}%`;


        draggingBed.style.top =
            `${y}%`;

    }
);


document.addEventListener(
    "mouseup",
    function() {

        draggingBed =
            null;

    }
);


/* =========================================================
   ADD BED
========================================================= */

function addBed() {

    if (
        !currentPlot ||
        !bedEditMode
    ) {
        return;
    }


    const container =
        document.getElementById(
            "bedMarkersContainer"
        );


    const numbers =
        [
            ...container.querySelectorAll(
                ".bed-marker"
            )
        ].map(
            m =>
                Number(
                    m.dataset.bed
                ) || 0
        );


    const newNumber =
        numbers.length
            ? Math.max(...numbers) + 1
            : 1;


    const marker =
        createBedMarker({

            number:
                newNumber,

            x:
                50,

            y:
                50

        });


    marker.classList.add(
        "editing"
    );


    container.appendChild(
        marker
    );


    selectBed(marker);

    updateBedCount();

}


/* =========================================================
   DELETE BED
========================================================= */

function deleteSelectedBed() {

    if (
        !bedEditMode ||
        !selectedBed
    ) {

        alert(
            "Select a bed marker first."
        );

        return;

    }


    const number =
        selectedBed.dataset.bed;


    if (
        !confirm(
            `Delete Bed ${number}?`
        )
    ) {
        return;
    }


    selectedBed.remove();


    selectedBed =
        null;


    [
        ...document.querySelectorAll(
            "#bedMarkersContainer .bed-marker"
        )
    ].forEach(
        (marker, index) => {

            marker.dataset.bed =
                String(index + 1);

            marker.textContent =
                index + 1;

            marker.title =
                `Bed ${index + 1}`;

        }
    );


    updateBedCount();

    updateBedEditUI();

}


/* =========================================================
   SAVE BEDS
========================================================= */

function saveBeds() {

    if (
        !currentPlot ||
        !bedEditMode
    ) {
        return;
    }


    const stored =
        getStoredBeds();


    stored[currentPlot] =
        collectCurrentBedPositions();


    saveStoredBeds(
        stored
    );


    bedSnapshot =
        null;


    bedEditMode =
        false;


    selectedBed =
        null;


    draggingBed =
        null;


    document.getElementById(
        "plotPopup"
    ).classList.remove(
        "bed-editing"
    );


    document.querySelectorAll(
        "#bedMarkersContainer .bed-marker"
    ).forEach(
        marker => {

            marker.classList.remove(
                "editing",
                "selected"
            );

        }
    );


    updateBedEditUI();


    alert(
        "Bed marker positions saved successfully."
    );

}


/* =========================================================
   CANCEL BED EDITING
========================================================= */

function cancelBedEditing() {

    if (!bedEditMode) return;


    if (
        !confirm(
            "Discard the current bed marker changes?"
        )
    ) {
        return;
    }


    restoreBedSnapshot();


    bedEditMode =
        false;


    selectedBed =
        null;


    draggingBed =
        null;


    bedSnapshot =
        null;


    document.getElementById(
        "plotPopup"
    ).classList.remove(
        "bed-editing"
    );


    updateBedEditUI();

}


/* =========================================================
   =========================================================
   CROP PROFILE SYSTEM
   =========================================================
========================================================= */


/*
|--------------------------------------------------------------------------
| CROP PROFILE STORAGE
|--------------------------------------------------------------------------
|
| Data is currently saved in localStorage.
| This allows the farmer to:
|
| - upload a crop picture
| - update crop details
| - enter planted date
| - enter weeks planted
| - change status
|
*/

const CROP_STORAGE_KEY =
    "sanctuarioCropProfiles";


let currentCropBed =
    null;


let cropProfileSnapshot =
    null;


function getStoredCropProfiles() {

    try {

        const value =
            JSON.parse(
                localStorage.getItem(
                    CROP_STORAGE_KEY
                )
            );


        return value &&
               typeof value === "object" &&
               !Array.isArray(value)
            ? value
            : {};

    } catch (error) {

        console.error(
            "Unable to load crop profiles:",
            error
        );

        return {};

    }

}


function saveStoredCropProfiles(data) {

    localStorage.setItem(
        CROP_STORAGE_KEY,
        JSON.stringify(data)
    );

}


/* =========================================================
   DEFAULT CROP DATA
========================================================= */

const defaultCropData = {

    1: {

        name:
            "LETTUCE",

        variety:
            "Majesta Variety",

        staff:
            "Mr. Villa",

        pcs:
            "5 Trays",

        germination:
            "79%",

        area:
            "A1",

        plantedDate:
            "2026-01-20",

        harvest:
            "2026-05-01",

        status:
            "Growing",

        image:
            ""

    },


    2: {

        name:
            "OKRA",

        variety:
            "Clemson Spineless",

        staff:
            "Ms. Liaga",

        pcs:
            "4 Trays",

        germination:
            "84%",

        area:
            "A2",

        plantedDate:
            "2026-01-24",

        harvest:
            "2026-05-08",

        status:
            "Growing",

        image:
            ""

    },


    3: {

        name:
            "PECHAY",

        variety:
            "Black Behi",

        staff:
            "Mr. Manalo",

        pcs:
            "6 Trays",

        germination:
            "91%",

        area:
            "A3",

        plantedDate:
            "2026-01-21",

        harvest:
            "2026-04-20",

        status:
            "Ready for Harvest",

        image:
            ""

    },


    4: {

        name:
            "CORN",

        variety:
            "Purple Gem F1",

        staff:
            "Mr. Villa",

        pcs:
            "10 Trays",

        germination:
            "87%",

        area:
            "A4",

        plantedDate:
            "2026-01-19",

        harvest:
            "2026-05-15",

        status:
            "Growing",

        image:
            ""

    },


    5: {

        name:
            "SPINACH",

        variety:
            "Bloomsdale",

        staff:
            "Ms. Liaga",

        pcs:
            "5 Trays",

        germination:
            "82%",

        area:
            "A5",

        plantedDate:
            "2026-01-25",

        harvest:
            "2026-05-10",

        status:
            "Growing",

        image:
            ""

    }

};


/* =========================================================
   GET CROP PROFILE
========================================================= */

function getCropProfile(bedNumber) {

    const stored =
        getStoredCropProfiles();


    const key =
        `${currentPlot}-${bedNumber}`;


    if (
        stored[key]
    ) {

        return stored[key];

    }


    if (
        defaultCropData[bedNumber]
    ) {

        return {
            ...defaultCropData[bedNumber]
        };

    }


    return {

        name:
            "Unassigned Crop",

        variety:
            "No variety assigned",

        staff:
            "Not assigned",

        pcs:
            "0",

        germination:
            "0%",

        area:
            `${currentPlot || ""}${bedNumber}`,

        plantedDate:
            getTodayDate(),

        harvest:
            "",

        status:
            "Planted",

        image:
            ""

    };

}


/* =========================================================
   DATE HELPERS
========================================================= */

function getTodayDate() {

    const now =
        new Date();


    const year =
        now.getFullYear();


    const month =
        String(
            now.getMonth() + 1
        ).padStart(2, "0");


    const day =
        String(
            now.getDate()
        ).padStart(2, "0");


    return `${year}-${month}-${day}`;

}


function parseDateLocal(dateString) {

    if (!dateString) {
        return null;
    }


    const parts =
        dateString.split("-");


    if (
        parts.length !== 3
    ) {
        return null;
    }


    const date =
        new Date(
            Number(parts[0]),
            Number(parts[1]) - 1,
            Number(parts[2])
        );


    if (
        Number.isNaN(
            date.getTime()
        )
    ) {
        return null;
    }


    return date;

}


/* =========================================================
   CALCULATE DAYS PLANTED
========================================================= */

function calculateDaysPlanted(plantedDate) {

    const start =
        parseDateLocal(
            plantedDate
        );


    if (!start) {
        return 0;
    }


    const today =
        new Date();


    const todayLocal =
        new Date(
            today.getFullYear(),
            today.getMonth(),
            today.getDate()
        );


    const difference =
        todayLocal.getTime() -
        start.getTime();


    return Math.max(
        0,
        Math.floor(
            difference /
            (1000 * 60 * 60 * 24)
        )
    );

}


/* =========================================================
   FORMAT DATE
========================================================= */

function formatDate(dateString) {

    const date =
        parseDateLocal(
            dateString
        );


    if (!date) {
        return "--";
    }


    return date.toLocaleDateString(
        "en-US",
        {
            month: "short",
            day: "numeric",
            year: "numeric"
        }
    );

}


/* =========================================================
   STATUS CLASS
========================================================= */

function getStatusClass(status) {

    switch (
        String(status || "")
            .toLowerCase()
    ) {

        case "planted":
            return "planted";

        case "growing":
            return "growing";

        case "ready for harvest":
            return "ready";

        case "harvested":
            return "harvested";

        case "failed":
            return "failed";

        default:
            return "";

    }

}


/* =========================================================
   OPEN CROP PROFILE
========================================================= */

function openCropProfile(bedNumber) {

    if (!currentPlot) {
        return;
    }


    currentCropBed =
        bedNumber;


    const crop =
        getCropProfile(
            bedNumber
        );


    renderCropProfile(
        crop
    );


    document.getElementById(
        "cropProfileOverlay"
    ).classList.add(
        "show"
    );


    document.getElementById(
        "cropEditForm"
    ).classList.remove(
        "show"
    );


    setCropProfileEditButtons(
        false
    );

}


/* =========================================================
   RENDER CROP PROFILE
========================================================= */

function renderCropProfile(crop) {

    const days =
        calculateDaysPlanted(
            crop.plantedDate
        );


    const weeks =
        Math.floor(
            days / 7
        );



    document.getElementById(
        "profileCropStaff"
    ).textContent =
        crop.staff || "Not assigned";


    document.getElementById(
        "profileCropPCS"
    ).textContent =
        crop.pcs || "0";


    document.getElementById(
        "profileCropGermination"
    ).textContent =
        crop.germination || "0%";


    document.getElementById(
        "profileCropArea"
    ).textContent =
        crop.area || "--";


    document.getElementById(
        "profileCropPlantedDate"
    ).textContent =
        formatDate(
            crop.plantedDate
        );


    document.getElementById(
        "profileCropHarvest"
    ).textContent =
        formatDate(
            crop.harvest
        );


    const statusElement =
        document.getElementById(
            "profileCropStatus"
        );


    statusElement.textContent =
        crop.status || "Planted";


    statusElement.className =
        "crop-status " +
        getStatusClass(
            crop.status
        );


    document.getElementById(
        "profileDaysPlanted"
    ).textContent =
        `${days} ${
            days === 1
                ? "day"
                : "days"
        }`;


    document.getElementById(
        "profileWeeksPlanted"
    ).textContent =
        `${weeks} ${
            weeks === 1
                ? "week"
                : "weeks"
        }`;


    document.getElementById(
        "cropTimelineText"
    ).textContent =
        `${days} days planted • ${weeks} weeks`;


    /*
     * Progress is based on a 12-week
     * general visualization.
     *
     * This is only a visual timeline.
     */

    const progress =
        Math.min(
            100,
            (weeks / 12) * 100
        );


    document.getElementById(
        "cropProgressBar"
    ).style.width =
        `${progress}%`;


    updateCropImage(
        crop.image
    );

}


/* =========================================================
   UPDATE CROP IMAGE
========================================================= */

function updateCropImage(image) {

    const img =
        document.getElementById(
            "cropProfileImage"
        );


    const placeholder =
        document.getElementById(
            "cropImagePlaceholder"
        );


    if (image) {

        img.src =
            image;


        img.style.display =
            "block";


        placeholder.style.display =
            "none";

    } else {

        img.src =
            "";


        img.style.display =
            "none";


        placeholder.style.display =
            "flex";

    }

}


/* =========================================================
   EDIT CROP PROFILE
========================================================= */

function startCropProfileEditing() {

    const crop =
        getCropProfile(
            currentCropBed
        );


    document.getElementById(
        "editCropName"
    ).value =
        crop.name || "";


    document.getElementById(
        "editCropVariety"
    ).value =
        crop.variety || "";


    document.getElementById(
        "editCropStaff"
    ).value =
        crop.staff || "";


    document.getElementById(
        "editCropPCS"
    ).value =
        crop.pcs || "";


    document.getElementById(
        "editCropGermination"
    ).value =
        crop.germination || "";


    document.getElementById(
        "editCropArea"
    ).value =
        crop.area || "";


    document.getElementById(
        "editCropPlantedDate"
    ).value =
        crop.plantedDate || getTodayDate();


    document.getElementById(
        "editCropWeeks"
    ).value =
        Math.floor(
            calculateDaysPlanted(
                crop.plantedDate
            ) / 7
        );


    document.getElementById(
        "editCropHarvest"
    ).value =
        crop.harvest || "";


    document.getElementById(
        "editCropStatus"
    ).value =
        crop.status || "Planted";


    document.getElementById(
        "cropEditForm"
    ).classList.add(
        "show"
    );


    setCropProfileEditButtons(
        true
    );

}


/* =========================================================
   EDIT BUTTON UI
========================================================= */

function setCropProfileEditButtons(editing) {

    document.getElementById(
        "editCropProfileBtn"
    ).style.display =
        editing
            ? "none"
            : "inline-flex";


    document.getElementById(
        "saveCropProfileBtn"
    ).style.display =
        editing
            ? "inline-flex"
            : "none";


    document.getElementById(
        "cancelCropProfileBtn"
    ).style.display =
        editing
            ? "inline-flex"
            : "none";

}


/* =========================================================
   SAVE CROP PROFILE
========================================================= */

function saveCropProfile() {

    if (
        !currentPlot ||
        !currentCropBed
    ) {
        return;
    }


    const plantedDate =
        document.getElementById(
            "editCropPlantedDate"
        ).value;


    let weeks =
        Number(
            document.getElementById(
                "editCropWeeks"
            ).value
        );


    if (
        !Number.isFinite(weeks) ||
        weeks < 0
    ) {
        weeks = 0;
    }


    /*
     * If the farmer changes the
     * weeks planted field, update
     * the planted date automatically.
     *
     * Example:
     *
     * 4 weeks
     * =
     * today minus 28 days
     */

    let finalPlantedDate =
        plantedDate;


    const currentWeeks =
        Math.floor(
            calculateDaysPlanted(
                plantedDate
            ) / 7
        );


    if (
        weeks !== currentWeeks
    ) {

        const today =
            new Date();


        today.setHours(
            0, 0, 0, 0
        );


        today.setDate(
            today.getDate() -
            (weeks * 7)
        );


        finalPlantedDate =
            `${today.getFullYear()}-${
                String(
                    today.getMonth() + 1
                ).padStart(2, "0")
            }-${
                String(
                    today.getDate()
                ).padStart(2, "0")
            }`;

    }


    const crop = {

        name:
            document.getElementById(
                "editCropName"
            ).value.trim() ||
            "Unnamed Crop",


        variety:
            document.getElementById(
                "editCropVariety"
            ).value.trim() ||
            "No variety",


        staff:
            document.getElementById(
                "editCropStaff"
            ).value.trim() ||
            "Not assigned",


        pcs:
            document.getElementById(
                "editCropPCS"
            ).value.trim() ||
            "0",


        germination:
            document.getElementById(
                "editCropGermination"
            ).value.trim() ||
            "0%",


        area:
            document.getElementById(
                "editCropArea"
            ).value.trim() ||
            `${currentPlot}${currentCropBed}`,


        plantedDate:
            finalPlantedDate,


        harvest:
            document.getElementById(
                "editCropHarvest"
            ).value,


        status:
            document.getElementById(
                "editCropStatus"
            ).value,


        image:
            getCropProfile(
                currentCropBed
            ).image || ""

    };


    const stored =
        getStoredCropProfiles();


    const key =
        `${currentPlot}-${currentCropBed}`;


    stored[key] =
        crop;


    saveStoredCropProfiles(
        stored
    );


    renderCropProfile(
        crop
    );


    document.getElementById(
        "cropEditForm"
    ).classList.remove(
        "show"
    );


    setCropProfileEditButtons(
        false
    );


    alert(
        "Crop profile saved successfully."
    );

}


/* =========================================================
   CANCEL CROP PROFILE EDITING
========================================================= */

function cancelCropProfileEditing() {

    document.getElementById(
        "cropEditForm"
    ).classList.remove(
        "show"
    );


    setCropProfileEditButtons(
        false
    );


    renderCropProfile(
        getCropProfile(
            currentCropBed
        )
    );

}


/* =========================================================
   CLOSE CROP PROFILE
========================================================= */

function closeCropProfile() {

    document.getElementById(
        "cropProfileOverlay"
    ).classList.remove(
        "show"
    );


    currentCropBed =
        null;

}


/* =========================================================
   IMAGE UPLOAD
========================================================= */

document.getElementById(
    "uploadCropImageBtn"
).addEventListener(
    "click",
    function() {

        document.getElementById(
            "cropImageInput"
        ).click();

    }
);


document.getElementById(
    "cropImageInput"
).addEventListener(
    "change",
    function(event) {

        const file =
            event.target.files[0];


        if (!file) return;


        if (
            !file.type.startsWith(
                "image/"
            )
        ) {

            alert(
                "Please select an image file."
            );

            return;

        }


        /*
         * Limit to 5 MB because
         * localStorage has limited capacity.
         */

        if (
            file.size >
            5 * 1024 * 1024
        ) {

            alert(
                "Please choose an image smaller than 5 MB."
            );

            event.target.value = "";

            return;

        }


        const reader =
            new FileReader();


        reader.onload =
            function(e) {

                if (
                    !currentPlot ||
                    !currentCropBed
                ) {
                    return;
                }


                const stored =
                    getStoredCropProfiles();


                const key =
                    `${currentPlot}-${currentCropBed}`;


                const crop =
                    getCropProfile(
                        currentCropBed
                    );


                crop.image =
                    e.target.result;


                stored[key] =
                    crop;


                saveStoredCropProfiles(
                    stored
                );


                updateCropImage(
                    crop.image
                );


                alert(
                    "Crop picture updated successfully."
                );

            };


        reader.readAsDataURL(
            file
        );


        event.target.value =
            "";

    }
);


/* =========================================================
   REMOVE CROP IMAGE
========================================================= */

document.getElementById(
    "removeCropImageBtn"
).addEventListener(
    "click",
    function() {

        if (
            !currentPlot ||
            !currentCropBed
        ) {
            return;
        }


        if (
            !confirm(
                "Remove this crop picture?"
            )
        ) {
            return;
        }


        const stored =
            getStoredCropProfiles();


        const key =
            `${currentPlot}-${currentCropBed}`;


        const crop =
            getCropProfile(
                currentCropBed
            );


        crop.image =
            "";


        stored[key] =
            crop;


        saveStoredCropProfiles(
            stored
        );


        updateCropImage(
            ""
        );

    }
);


/* =========================================================
   LIVE WEEKS CALCULATION
========================================================= */

document.getElementById(
    "editCropPlantedDate"
).addEventListener(
    "change",
    function() {

        const weeks =
            Math.floor(
                calculateDaysPlanted(
                    this.value
                ) / 7
            );


        document.getElementById(
            "editCropWeeks"
        ).value =
            weeks;

    }
);


/* =========================================================
   CROP DATA - COMPATIBILITY
========================================================= */

const cropData =
    defaultCropData;


function openPlant(id) {

    openCropProfile(
        Number(id)
    );

}


function closePlant() {

    closeCropProfile();

}


/* =========================================================
   NURSERY
========================================================= */

function openNursery() {

    document.getElementById(
        "nurseryOverlay"
    ).classList.add(
        "show"
    );

}


function closeNursery() {

    document.getElementById(
        "nurseryOverlay"
    ).classList.remove(
        "show"
    );

}


/* =========================================================
   LIVESTOCK
========================================================= */

/* =========================================================
   LIVESTOCK DATA + STORAGE
========================================================= */

const LIVESTOCK_STORAGE_KEY =
    "sanctuarioLivestockProfiles";

const LIVESTOCK_HOUSE_STORAGE_KEY =
    "sanctuarioLivestockHousePositions";

let currentLivestockHouse = null;
let currentLivestockId = null;
let livestockProfileSnapshot = null;

const defaultLivestockHouses = {

    "goat-house": {
        title: "Goat House",
        image: "<?= $basePath ?>farmMap/goat_house.jpg?v=3",
        description: "Goat housing area.",
        animals: [
            {
                id: "gino",
                name: "Gino",
                sex: "Buck",
                dob: "2025-03-01",
                breed: "Native",
                weight: "25 kg",
                notes: "Happy goat",
                image: "",
                x: 31,
                y: 45
            }
        ]
    },

    "goat-house-2": {
        title: "Goat House 2",
        image: "<?= $basePath ?>farmMap/goat_house.jpg?v=3",
        description: "Second goat housing area.",
        animals: []
    },

    "rabbit-house": {
        title: "Rabbit House",
        image: "<?= $basePath ?>farmMap/rabbit_house.jpg",
        description: "Rabbit housing area.",
        animals: []
    },

    "poultry-house": {
        title: "Poultry House",
        image: "<?= $basePath ?>farmMap/poultry_house.jpg",
        description: "Poultry housing area.",
        animals: []
    },

    "swine-house": {
        title: "Swine House",
        image: "<?= $basePath ?>farmMap/swine_house.jpg",
        description: "Swine housing area.",
        animals: []
    }
};

function getStoredLivestockProfiles() {
    try {
        const value = JSON.parse(
            localStorage.getItem(LIVESTOCK_STORAGE_KEY)
        );
        return value && typeof value === "object" && !Array.isArray(value)
            ? value
            : {};
    } catch (error) {
        console.error("Unable to load livestock profiles:", error);
        return {};
    }
}

function saveStoredLivestockProfiles(data) {
    localStorage.setItem(
        LIVESTOCK_STORAGE_KEY,
        JSON.stringify(data)
    );
}

function getLivestockHouseData(houseId) {

    const base = defaultLivestockHouses[houseId];

    if (!base) return null;

    const stored = getStoredLivestockProfiles();

    const animals = Array.isArray(stored[houseId])
        ? stored[houseId]
        : base.animals.map(animal => ({ ...animal }));

    return {
        ...base,
        animals
    };
}

function saveLivestockHouseAnimals(houseId, animals) {
    const stored = getStoredLivestockProfiles();
    stored[houseId] = animals;
    saveStoredLivestockProfiles(stored);
}

function calculateLivestockAge(dob) {

    if (!dob) return "--";

    const birth = parseDateLocal(dob);
    if (!birth) return "--";

    const today = new Date();
    const todayLocal = new Date(
        today.getFullYear(),
        today.getMonth(),
        today.getDate()
    );

    let months =
        (todayLocal.getFullYear() - birth.getFullYear()) * 12 +
        (todayLocal.getMonth() - birth.getMonth());

    if (todayLocal.getDate() < birth.getDate()) {
        months--;
    }

    months = Math.max(0, months);

    if (months < 12) {
        return `${months} ${months === 1 ? "month" : "months"}`;
    }

    const years = Math.floor(months / 12);
    const remainingMonths = months % 12;

    if (!remainingMonths) {
        return `${years} ${years === 1 ? "year" : "years"}`;
    }

    return `${years} ${years === 1 ? "year" : "years"} ${remainingMonths} ${remainingMonths === 1 ? "month" : "months"}`;
}

function formatLivestockDate(dateString) {

    if (!dateString) return "--";

    const date = parseDateLocal(dateString);
    if (!date) return dateString;

    return date.toLocaleDateString("en-US", {
        month: "long",
        day: "numeric",
        year: "numeric"
    });
}

function createLivestockMarker(animal) {

    const marker = document.createElement("button");

    marker.type = "button";
    marker.className = "livestock-house-marker";
    marker.dataset.id = animal.id;
    marker.dataset.name = animal.name || "Livestock";
    marker.style.left = `${Number(animal.x) || 0}%`;
    marker.style.top = `${Number(animal.y) || 0}%`;
    marker.textContent = "🐐";
    marker.title = animal.name || "Livestock";

    marker.addEventListener("click", function(event) {
        event.stopPropagation();
        openLivestockProfile(animal.id);
    });

    return marker;
}

function renderLivestockMarkers(houseId) {

    const container = document.getElementById(
        "livestockMarkersContainer"
    );

    if (!container) return;

    container.innerHTML = "";

    const house = getLivestockHouseData(houseId);

    if (!house) return;

    house.animals.forEach(animal => {
        container.appendChild(
            createLivestockMarker(animal)
        );
    });

    const count = document.getElementById("livestockCount");

    if (count) {
        count.textContent = `${house.animals.length} ${house.animals.length === 1 ? "animal" : "animals"}`;
    }
}

function openLivestock(houseId) {

    const house = getLivestockHouseData(houseId);

    if (!house) {
        alert("This livestock house does not have information yet.");
        return;
    }

    currentLivestockHouse = houseId;

    document.getElementById("livestockHouseImage").src = house.image;
    document.getElementById("livestockHouseTitle").textContent = house.title;
    document.getElementById("livestockHouseDescription").textContent = house.description;

    renderLivestockMarkers(houseId);

    document.getElementById("livestockOverlay").classList.add("show");
}

function closeLivestock() {

    closeLivestockProfile();

    currentLivestockHouse = null;

    document.getElementById("livestockOverlay").classList.remove("show");
}

function getCurrentLivestock() {

    if (!currentLivestockHouse || !currentLivestockId) {
        return null;
    }

    const house = getLivestockHouseData(currentLivestockHouse);

    if (!house) return null;

    return house.animals.find(
        animal => animal.id === currentLivestockId
    ) || null;
}

function updateLivestockImage(image) {

    const img = document.getElementById("livestockProfileImage");
    const placeholder = document.getElementById("livestockImagePlaceholder");

    if (image) {
        img.src = image;
        img.style.display = "block";
        placeholder.style.display = "none";
    } else {
        img.src = "";
        img.style.display = "none";
        placeholder.style.display = "flex";
    }
}

function renderLivestockProfile(animal) {

    if (!animal) return;

    document.getElementById("profileLivestockName").textContent =
        animal.name || "Unnamed Livestock";

    document.getElementById("profileLivestockType").textContent =
        animal.sex || "Livestock";

    document.getElementById("profileLivestockDOB").textContent =
        formatLivestockDate(animal.dob);

    document.getElementById("profileLivestockBreed").textContent =
        animal.breed || "--";

    document.getElementById("profileLivestockAge").textContent =
        calculateLivestockAge(animal.dob);

    document.getElementById("profileLivestockWeight").textContent =
        animal.weight || "--";

    document.getElementById("profileLivestockNotes").textContent =
        animal.notes || "No notes";

    updateLivestockImage(animal.image || "");
}

function openLivestockProfile(animalId) {

    currentLivestockId = animalId;

    const animal = getCurrentLivestock();

    if (!animal) {
        alert("Livestock profile not found.");
        return;
    }

    renderLivestockProfile(animal);

    document.getElementById("livestockEditForm").classList.remove("show");
    setLivestockProfileEditButtons(false);

    document.getElementById("livestockProfileOverlay").classList.add("show");
}

function closeLivestockProfile() {

    document.getElementById("livestockProfileOverlay").classList.remove("show");
    document.getElementById("livestockEditForm").classList.remove("show");
    setLivestockProfileEditButtons(false);

    currentLivestockId = null;
    livestockProfileSnapshot = null;
}

function setLivestockProfileEditButtons(editing) {

    document.getElementById("editLivestockProfileBtn").style.display =
        editing ? "none" : "inline-flex";

    document.getElementById("saveLivestockProfileBtn").style.display =
        editing ? "inline-flex" : "none";

    document.getElementById("cancelLivestockProfileBtn").style.display =
        editing ? "inline-flex" : "none";
}

function startLivestockProfileEditing() {

    const animal = getCurrentLivestock();

    if (!animal) return;

    livestockProfileSnapshot = JSON.parse(
        JSON.stringify(animal)
    );

    document.getElementById("editLivestockName").value = animal.name || "";
    document.getElementById("editLivestockSex").value = animal.sex || "Buck";
    document.getElementById("editLivestockDOB").value = animal.dob || "";
    document.getElementById("editLivestockBreed").value = animal.breed || "";
    document.getElementById("editLivestockWeight").value = animal.weight || "";
    document.getElementById("editLivestockNotes").value = animal.notes || "";

    document.getElementById("livestockEditForm").classList.add("show");
    setLivestockProfileEditButtons(true);
}

function saveLivestockProfile() {

    const animal = getCurrentLivestock();

    if (!animal || !currentLivestockHouse) return;

    animal.name =
        document.getElementById("editLivestockName").value.trim() || "Unnamed Livestock";

    animal.sex =
        document.getElementById("editLivestockSex").value || "Livestock";

    animal.dob =
        document.getElementById("editLivestockDOB").value || "";

    animal.breed =
        document.getElementById("editLivestockBreed").value.trim() || "--";

    animal.weight =
        document.getElementById("editLivestockWeight").value.trim() || "--";

    animal.notes =
        document.getElementById("editLivestockNotes").value.trim() || "";

    saveLivestockHouseAnimals(
        currentLivestockHouse,
        getLivestockHouseData(currentLivestockHouse).animals.map(item =>
            item.id === animal.id ? animal : item
        )
    );

    renderLivestockProfile(animal);
    renderLivestockMarkers(currentLivestockHouse);

    document.getElementById("livestockEditForm").classList.remove("show");
    setLivestockProfileEditButtons(false);

    livestockProfileSnapshot = null;

    alert("Livestock profile saved successfully.");
}

function cancelLivestockProfileEditing() {

    if (!livestockProfileSnapshot) {
        document.getElementById("livestockEditForm").classList.remove("show");
        setLivestockProfileEditButtons(false);
        return;
    }

    const house = getLivestockHouseData(currentLivestockHouse);

    if (house) {
        const restored = house.animals.map(item =>
            item.id === livestockProfileSnapshot.id
                ? { ...livestockProfileSnapshot }
                : item
        );

        saveLivestockHouseAnimals(
            currentLivestockHouse,
            restored
        );

        renderLivestockProfile(
            restored.find(item => item.id === currentLivestockId)
        );

        renderLivestockMarkers(currentLivestockHouse);
    }

    document.getElementById("livestockEditForm").classList.remove("show");
    setLivestockProfileEditButtons(false);
    livestockProfileSnapshot = null;
}



/* =========================================================
   RIGHT SIDE PLOT INFO
========================================================= */

function updateSidePlotInfo(plot) {

    const data =
        plotData[plot];


    if (!data) return;


    const title =
        document.getElementById(
            "sidePlotTitle"
        );


    const description =
        document.getElementById(
            "sidePlotDescription"
        );


    const area =
        document.getElementById(
            "sidePlotArea"
        );


    const beds =
        document.getElementById(
            "sidePlotBeds"
        );


    if (title) {

        title.textContent =
            data.title;

    }


    if (description) {

        description.textContent =
            data.description;

    }


    if (area) {

        area.textContent =
            data.area;

    }


    if (beds) {

        const bedCount =
            getBedsForPlot(
                plot
            ).length;


        beds.textContent =
            bedCount;

    }

}


/* =========================================================
   MAP MARKER CLICK
========================================================= */

function handleMapMarkerClick(marker) {

    if (mapEditMode) {
        return;
    }


    const type =
        marker.dataset.marker;


    const id =
        marker.dataset.id;


    if (
        type === "plot"
    ) {

        openPlot(id);

        return;

    }


    if (
        type === "nursery"
    ) {

        openNursery();

        return;

    }


    if (
        type === "livestock"
    ) {

        openLivestock(
            id
        );

        return;

    }


    alert(
        `${marker.dataset.name || "Marker"} selected.`
    );

}


/* =========================================================
   MAP EDITING
========================================================= */

let mapEditMode =
    false;


let mapEditTool =
    "move";


let draggingMarker =
    null;


let editSnapshot =
    null;


const MAP_STORAGE_KEY =
    "sanctuarioFarmMap";


/* =========================================================
   SNAPSHOT
========================================================= */

function createEditSnapshot() {

    editSnapshot =
        [
            ...document.querySelectorAll(
                ".editable-marker"
            )
        ].map(
            marker => ({

                id:
                    marker.id,

                left:
                    marker.style.left,

                top:
                    marker.style.top,

                name:
                    marker.dataset.name || "",

                marker:
                    marker.dataset.marker || "",

                idData:
                    marker.dataset.id || "",

                description:
                    marker.dataset.description || "",

                area:
                    marker.dataset.area || "",

                isNew:
                    marker.classList.contains(
                        "new-marker"
                    ),

                plotData:
                    marker.dataset.marker === "plot" &&
                    plotData[
                        marker.dataset.id
                    ]
                        ? {
                            ...plotData[
                                marker.dataset.id
                            ]
                        }
                        : null

            })
        );

}


/* =========================================================
   TOGGLE MAP EDIT
========================================================= */

function toggleMapEditMode() {

    mapEditMode
        ? leaveMapEditMode()
        : enterMapEditMode();

}


function enterMapEditMode() {

    mapEditMode =
        true;


    mapEditTool =
        "move";


    createEditSnapshot();


    const button =
        document.getElementById(
            "editMapBtn"
        );


    document.getElementById(
        "mapEditControls"
    ).classList.add(
        "show"
    );


    button.classList.add(
        "editing"
    );


    button.innerHTML =
        '<i class="fa-solid fa-eye"></i> View Map';


    document.getElementById(
        "mapEditStatus"
    ).textContent =
        "Move — Drag a marker";


    mapContainer.classList.add(
        "edit-mode"
    );


    enableEditableMarkers();

}


function leaveMapEditMode() {

    mapEditMode =
        false;


    mapEditTool =
        "move";


    draggingMarker =
        null;


    document.getElementById(
        "mapEditControls"
    ).classList.remove(
        "show"
    );


    const button =
        document.getElementById(
            "editMapBtn"
        );


    button.classList.remove(
        "editing"
    );


    button.innerHTML =
        '<i class="fa-solid fa-pen-to-square"></i> Edit Map';


    document.getElementById(
        "mapEditStatus"
    ).textContent =
        "View Mode";


    mapContainer.classList.remove(
        "edit-mode"
    );


    editSnapshot =
        null;

}


/* =========================================================
   MAP EDIT TOOL
========================================================= */

function setMapEditTool(tool) {

    if (!mapEditMode) {
        return;
    }


    mapEditTool =
        [
            "plot",
            "marker",
            "move"
        ].includes(tool)
            ? tool
            : "move";


    const status =
        document.getElementById(
            "mapEditStatus"
        );


    if (
        mapEditTool === "plot"
    ) {

        status.textContent =
            "Add Plot — Click the map";

    } else if (
        mapEditTool === "marker"
    ) {

        status.textContent =
            "Add Marker — Click the map";

    } else {

        status.textContent =
            "Move — Drag a marker";

    }

}


/* =========================================================
   MAP POSITION
========================================================= */

function getMapPosition(event) {

    const rect =
        mainMapLayer.getBoundingClientRect();


    if (
        !rect.width ||
        !rect.height
    ) {

        return {
            x: 0,
            y: 0
        };

    }


    return {

        x:
            Math.max(
                0,
                Math.min(
                    100,
                    (
                        (event.clientX - rect.left) /
                        rect.width
                    ) * 100
                )
            ),


        y:
            Math.max(
                0,
                Math.min(
                    100,
                    (
                        (event.clientY - rect.top) /
                        rect.height
                    ) * 100
                )
            )

    };

}


/* =========================================================
   ADD NEW PLOT
========================================================= */

function addNewPlot(event) {

    const position =
        getMapPosition(
            event
        );


    const name =
        prompt(
            "Enter plot name:",
            "Plot D"
        );


    if (
        !name ||
        !name.trim()
    ) {
        return;
    }


    const description =
        prompt(
            "Enter plot description:",
            "Crop Production Area"
        ) || "";


    const area =
        prompt(
            "Enter plot area:",
            "300m²"
        ) || "";


    const bedsInput =
        prompt(
            "How many beds does this plot have?",
            "10"
        );


    const beds =
        Math.max(
            0,
            Number.parseInt(
                bedsInput,
                10
            ) || 0
        );


    const id =
        `plot-${Date.now()}`;


    plotData[id] = {

        image:
            "<?= $basePath ?>farmMap/plot_a.jpg",

        title:
            name.trim(),

        description,

        beds,

        area

    };


    const marker =
        document.createElement(
            "button"
        );


    marker.type =
        "button";


    marker.className =
        "main-marker plot-marker editable-marker new-marker";


    marker.id =
        id;


    marker.dataset.marker =
        "plot";


    marker.dataset.id =
        id;


    marker.dataset.name =
        name.trim();


    marker.dataset.description =
        description;


    marker.dataset.area =
        area;


    marker.style.left =
        `${position.x}%`;


    marker.style.top =
        `${position.y}%`;


    marker.innerHTML =
        `
            <img
                src="<?= $basePath ?>markers/plot_a.png"
                alt="${escapeHTML(name.trim())}"
            >

            <span>
                ${escapeHTML(name.trim())}
            </span>
        `;


    marker.addEventListener(
        "click",
        function(event) {

            event.stopPropagation();

            handleMapMarkerClick(
                marker
            );

        }
    );


    mainMapLayer.appendChild(
        marker
    );


    enableMarkerDragging(
        marker
    );

}


/* =========================================================
   ADD NEW MARKER
========================================================= */

function addNewMarker(event) {

    const position =
        getMapPosition(
            event
        );


    const name =
        prompt(
            "Enter marker name:",
            "New Marker"
        );


    if (
        !name ||
        !name.trim()
    ) {
        return;
    }


    let type =
        (
            prompt(
                "Enter marker type:\n\nlivestock\nnursery\nother",
                "other"
            ) ||
            "other"
        )
        .toLowerCase()
        .trim();


    if (
        ![
            "livestock",
            "nursery",
            "other"
        ].includes(type)
    ) {

        type =
            "other";

    }


    const id =
        `marker-${Date.now()}`;


    const marker =
        document.createElement(
            "button"
        );


    marker.type =
        "button";


    marker.className =
        `main-marker editable-marker new-marker${
            type === "livestock"
                ? " livestock-marker"
                : ""
        }`;


    marker.id =
        id;


    marker.dataset.marker =
        type;


    marker.dataset.id =
        id;


    marker.dataset.name =
        name.trim();


    marker.style.left =
        `${position.x}%`;


    marker.style.top =
        `${position.y}%`;


    const icon =
        type === "livestock"
            ? "fa-cow"
            : type === "nursery"
                ? "fa-seedling"
                : "fa-location-dot";


    marker.innerHTML =
        `
            <div class="new-map-icon">
                <i class="fa-solid ${icon}"></i>
            </div>

            <span>
                ${escapeHTML(name.trim())}
            </span>
        `;


    marker.addEventListener(
        "click",
        function(event) {

            event.stopPropagation();

            handleMapMarkerClick(
                marker
            );

        }
    );


    mainMapLayer.appendChild(
        marker
    );


    enableMarkerDragging(
        marker
    );

}


/* =========================================================
   ESCAPE HTML
========================================================= */

function escapeHTML(value) {

    const div =
        document.createElement(
            "div"
        );


    div.textContent =
        String(
            value ?? ""
        );


    return div.innerHTML;

}


/* =========================================================
   ENABLE EDITABLE MARKERS
========================================================= */

function enableEditableMarkers() {

    document.querySelectorAll(
        ".editable-marker"
    ).forEach(
        enableMarkerDragging
    );

}


/* =========================================================
   ENABLE MARKER DRAGGING
========================================================= */

function enableMarkerDragging(marker) {

    if (
        marker.dataset.dragReady === "true"
    ) {

        return;

    }


    marker.dataset.dragReady =
        "true";


    marker.addEventListener(
        "mousedown",
        function(event) {

            if (
                !mapEditMode ||
                mapEditTool !== "move"
            ) {
                return;
            }


            draggingMarker =
                marker;


            event.preventDefault();

            event.stopPropagation();

        }
    );

}


/* =========================================================
   DRAG MARKER
========================================================= */

document.addEventListener(
    "mousemove",
    function(event) {

        if (
            !draggingMarker ||
            !mapEditMode ||
            mapEditTool !== "move"
        ) {
            return;
        }


        const rect =
            mainMapLayer.getBoundingClientRect();


        if (
            !rect.width ||
            !rect.height
        ) {
            return;
        }


        draggingMarker.style.left =
            `${Math.max(
                0,
                Math.min(
                    100,
                    (
                        (event.clientX - rect.left) /
                        rect.width
                    ) * 100
                )
            )}%`;


        draggingMarker.style.top =
            `${Math.max(
                0,
                Math.min(
                    100,
                    (
                        (event.clientY - rect.top) /
                        rect.height
                    ) * 100
                )
            )}%`;

    }
);


document.addEventListener(
    "mouseup",
    function() {

        draggingMarker =
            null;

    }
);


/* =========================================================
   MAP STORAGE
========================================================= */

function getStoredMapData() {

    try {

        const value =
            JSON.parse(
                localStorage.getItem(
                    MAP_STORAGE_KEY
                )
            );


        return Array.isArray(value)
            ? value
            : [];

    } catch (error) {

        console.error(
            "Unable to load map positions:",
            error
        );

        return [];

    }

}


function saveAllMapData() {

    const mapData =
        [
            ...document.querySelectorAll(
                ".editable-marker"
            )
        ].map(
            marker => ({

                id:
                    marker.id,

                type:
                    marker.dataset.marker ||
                    "other",

                name:
                    marker.dataset.name ||
                    "Marker",

                left:
                    marker.style.left,

                top:
                    marker.style.top,

                description:
                    marker.dataset.description ||
                    "",

                area:
                    marker.dataset.area ||
                    "",

                plot:
                    marker.dataset.marker === "plot" &&
                    plotData[
                        marker.dataset.id
                    ]
                        ? {
                            ...plotData[
                                marker.dataset.id
                            ]
                        }
                        : null

            })
        );


    localStorage.setItem(
        MAP_STORAGE_KEY,
        JSON.stringify(
            mapData
        )
    );

}


/* =========================================================
   RESTORE MAP SNAPSHOT
========================================================= */

function restoreEditSnapshot() {

    if (
        !Array.isArray(
            editSnapshot
        )
    ) {
        return;
    }


    document.querySelectorAll(
        ".new-marker"
    ).forEach(
        marker => marker.remove()
    );


    editSnapshot.forEach(
        data => {

            const marker =
                document.getElementById(
                    data.id
                );


            if (!marker) return;


            marker.style.left =
                data.left;


            marker.style.top =
                data.top;


            marker.dataset.name =
                data.name;


            marker.dataset.marker =
                data.marker;


            marker.dataset.id =
                data.idData;


            marker.dataset.description =
                data.description;


            marker.dataset.area =
                data.area;


            const label =
                marker.querySelector(
                    "span"
                );


            if (label) {

                label.textContent =
                    data.name;

            }


            if (
                data.plotData
            ) {

                plotData[
                    data.idData
                ] = {
                    ...data.plotData
                };

            }

        }
    );


    editSnapshot =
        null;

}


/* =========================================================
   SAVE MAP
========================================================= */

function saveEditableMap() {

    saveAllMapData();


    alert(
        "Farm map changes saved successfully."
    );


    leaveMapEditMode();

}


/* =========================================================
   CANCEL MAP
========================================================= */

function cancelMapEdit() {

    if (
        !confirm(
            "Discard your current changes?"
        )
    ) {
        return;
    }


    restoreEditSnapshot();


    leaveMapEditMode();

}


/* =========================================================
   LOAD SAVED MARKER
========================================================= */

function createLoadedMarker(data) {

    const marker =
        document.createElement(
            "button"
        );


    marker.type =
        "button";


    marker.id =
        data.id;


    marker.dataset.marker =
        data.type ||
        "other";


    marker.dataset.id =
        data.id;


    marker.dataset.name =
        data.name ||
        "Marker";


    marker.dataset.description =
        data.description ||
        "";


    marker.dataset.area =
        data.area ||
        "";


    marker.style.left =
        data.left ||
        "50%";


    marker.style.top =
        data.top ||
        "50%";


    if (
        data.type === "plot"
    ) {

        marker.className =
            "main-marker plot-marker editable-marker new-marker";


        marker.innerHTML =
            `
                <img
                    src="<?= $basePath ?>markers/plot_a.png"
                    alt="${escapeHTML(data.name || "Plot")}"
                >

                <span>
                    ${escapeHTML(data.name || "Plot")}
                </span>
            `;

    } else {

        marker.className =
            `main-marker editable-marker new-marker${
                data.type === "livestock"
                    ? " livestock-marker"
                    : ""
            }`;


        const icon =
            data.type === "livestock"
                ? "fa-cow"
                : data.type === "nursery"
                    ? "fa-seedling"
                    : "fa-location-dot";


        marker.innerHTML =
            `
                <div class="new-map-icon">
                    <i class="fa-solid ${icon}"></i>
                </div>

                <span>
                    ${escapeHTML(data.name || "Marker")}
                </span>
            `;

    }


    marker.addEventListener(
        "click",
        function(event) {

            event.stopPropagation();

            handleMapMarkerClick(
                marker
            );

        }
    );


    mainMapLayer.appendChild(
        marker
    );


    enableMarkerDragging(
        marker
    );


    return marker;

}


/* =========================================================
   LOAD SAVED MARKERS
========================================================= */

function loadEditableMarkers() {

    getStoredMapData()
        .forEach(
            data => {

                if (
                    !data ||
                    !data.id
                ) {
                    return;
                }


                if (
                    data.type === "plot" &&
                    data.plot
                ) {

                    plotData[
                        data.id
                    ] = {
                        ...data.plot
                    };

                }


                const existing =
                    document.getElementById(
                        data.id
                    );


                if (existing) {

                    existing.style.left =
                        data.left ||
                        existing.style.left;


                    existing.style.top =
                        data.top ||
                        existing.style.top;


                    existing.dataset.name =
                        data.name ||
                        existing.dataset.name;


                    existing.dataset.description =
                        data.description ||
                        existing.dataset.description ||
                        "";


                    existing.dataset.area =
                        data.area ||
                        existing.dataset.area ||
                        "";


                    const label =
                        existing.querySelector(
                            "span"
                        );


                    if (
                        label &&
                        data.name
                    ) {

                        label.textContent =
                            data.name;

                    }


                    return;

                }


                createLoadedMarker(
                    data
                );

            }
        );

}


/* =========================================================
   MAP ADD CLICK
========================================================= */

mapContainer.addEventListener(
    "click",
    function(event) {

        if (!mapEditMode) {
            return;
        }


        if (
            event.target.closest(
                ".main-marker"
            ) ||
            isOverlayTarget(
                event.target
            )
        ) {

            return;

        }


        if (
            !event.target.closest(
                "#mainMapLayer"
            )
        ) {

            return;

        }


        if (
            mapEditTool === "plot"
        ) {

            addNewPlot(
                event
            );

        } else if (
            mapEditTool === "marker"
        ) {

            addNewMarker(
                event
            );

        }

    }
);


/* =========================================================
   BUTTON EVENTS
========================================================= */

document.getElementById(
    "editMapBtn"
).addEventListener(
    "click",
    toggleMapEditMode
);


document.querySelectorAll(
    "#mapEditControls button[data-tool]"
).forEach(
    button => {

        button.addEventListener(
            "click",
            () =>
                setMapEditTool(
                    button.dataset.tool
                )
        );

    }
);


document.getElementById(
    "saveMapBtn"
).addEventListener(
    "click",
    saveEditableMap
);


document.getElementById(
    "cancelMapBtn"
).addEventListener(
    "click",
    cancelMapEdit
);


/* =========================================================
   BED BUTTONS
========================================================= */

document.getElementById(
    "startEditBedsBtn"
).addEventListener(
    "click",
    startBedEditing
);


document.getElementById(
    "addBedBtn"
).addEventListener(
    "click",
    addBed
);


document.getElementById(
    "deleteBedBtn"
).addEventListener(
    "click",
    deleteSelectedBed
);


document.getElementById(
    "saveBedsBtn"
).addEventListener(
    "click",
    saveBeds
);


document.getElementById(
    "cancelBedsBtn"
).addEventListener(
    "click",
    cancelBedEditing
);


/* =========================================================
   EXISTING MAP MARKERS
========================================================= */

document.querySelectorAll(
    ".main-marker"
).forEach(
    marker => {

        marker.addEventListener(
            "click",
            function(event) {

                event.stopPropagation();

                handleMapMarkerClick(
                    marker
                );

            }
        );

    }
);


/* =========================================================
   POPUP CLOSE BUTTONS
========================================================= */

document.getElementById(
    "closePlotBtn"
).addEventListener(
    "click",
    closePlot
);


document.getElementById(
    "closeNurseryBtn"
).addEventListener(
    "click",
    closeNursery
);


document.getElementById(
    "closeCropProfileBtn"
).addEventListener(
    "click",
    closeCropProfile
);


/* =========================================================
   CROP PROFILE BUTTONS
========================================================= */

document.getElementById(
    "editCropProfileBtn"
).addEventListener(
    "click",
    startCropProfileEditing
);


document.getElementById(
    "saveCropProfileBtn"
).addEventListener(
    "click",
    saveCropProfile
);


document.getElementById(
    "cancelCropProfileBtn"
).addEventListener(
    "click",
    cancelCropProfileEditing
);


/* =========================================================
   PLOT SELECT
========================================================= */

const plotSelect =
    document.getElementById(
        "plotSelect"
    );


if (plotSelect) {

    plotSelect.addEventListener(
        "change",
        function() {

            const selected =
                this.value;


            updateSidePlotInfo(
                selected
            );

        }
    );

}


/* =========================================================
   OVERLAY CLICK
========================================================= */

document.addEventListener(
    "click",
    function(event) {

        if (
            event.target.id ===
            "plotOverlay"
        ) {

            closePlot();

        }


        if (
            event.target.id ===
            "nurseryOverlay"
        ) {

            closeNursery();

        }


        if (
            event.target.id ===
            "cropProfileOverlay"
        ) {

            closeCropProfile();

        }


        if (
            event.target.id ===
            "livestockOverlay"
        ) {

            closeLivestock();

        }


        if (
            event.target.id ===
            "livestockProfileOverlay"
        ) {

            closeLivestockProfile();

        }

    }
);


/* =========================================================
   ESC KEY
========================================================= */

document.addEventListener(
    "keydown",
    function(event) {

        if (
            event.key !== "Escape"
        ) {
            return;
        }


        if (
            document.getElementById("livestockProfileOverlay")
                .classList.contains("show")
        ) {

            closeLivestockProfile();
            return;

        }


        if (
            document.getElementById("livestockOverlay")
                .classList.contains("show")
        ) {

            closeLivestock();
            return;

        }


        if (
            document
                .getElementById(
                    "cropProfileOverlay"
                )
                .classList.contains(
                    "show"
                )
        ) {

            closeCropProfile();

            return;

        }


        if (
            document
                .getElementById(
                    "plotOverlay"
                )
                .classList.contains(
                    "show"
                )
        ) {

            closePlot();

            return;

        }


        closeNursery();

    }
);


/* =========================================================
   INITIAL LOAD
========================================================= */

loadEditableMarkers();


requestAnimationFrame(
    function() {

        clampMap();

        updateMainMap();

    }
);


updateSidePlotInfo(
    "A"
);


updateBedEditUI();

</script>


</body>

</html>