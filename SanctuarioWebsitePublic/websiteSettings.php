
<?php
require __DIR__ . '/guard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Website Settings | Sanctuario</title>

    <link rel="stylesheet" href="../sidebar.css">
    <link rel="stylesheet" href="cms.css">

    <style>
        .settings-banner {
            background: #315b36 !important;
            color: #ffffff !important;
            padding: 22px 26px !important;
            border-radius: 14px !important;
            margin-bottom: 18px !important;
            box-shadow: 0 4px 14px rgba(31, 69, 36, .12) !important;
        }

        .settings-banner h1 {
            color: #ffffff !important;
            margin: 0 !important;
            font-size: 25px !important;
            font-weight: 700 !important;
        }

        .settings-banner p {
            color: #e2eee0 !important;
            margin: 5px 0 0 !important;
            font-size: 13px !important;
        }

        @media (max-width: 900px) {
            .settings-banner {
                padding: 18px !important;
            }
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/../sidebar.php'; ?>

    <main class="cms-main">
        <header class="settings-banner">
            <h1>Website Settings</h1>
            <p>Customize and manage your farm website.</p>
            <span id="draftStatus" aria-live="polite">Loading...</span>
        </header>

        <section class="cms-workspace">
            <div class="cms-toolbar">
                <div class="cms-toolbar-group">
                    <button
                        id="undo"
                        class="cms-btn"
                        type="button"
                        disabled
                    >
                        Undo
                    </button>

                    <button
                        id="redo"
                        class="cms-btn"
                        type="button"
                        disabled
                    >
                        Redo
                    </button>
                </div>

                <div class="cms-toolbar-group cms-toolbar-end">
                    <button id="cmsHistoryButton" class="cms-btn" type="button">History</button>
                    <button
                        id="previewMode"
                        class="cms-btn"
                        type="button"
                        aria-pressed="false"
                    >
                        <span id="previewLabel">Preview</span>
                    </button>

                    <span class="cms-divider"></span>

                    <button
                        id="saveDraft"
                        class="cms-btn cms-save"
                        type="button"
                    >
                        Save Draft
                    </button>

                    <button
                        id="publishAll"
                        class="cms-btn cms-publish"
                        type="button"
                    >
                        Publish
                    </button>
                </div>
            </div>

            <div
                id="previewNotice"
                class="cms-preview-notice"
                hidden
            >
                <span>Preview Mode — Editing is disabled.</span>

                <button id="exitPreview" type="button">
                    Back to Editor
                </button>
            </div>

            

            <div
                id="message"
                class="cms-message"
                role="status"
                aria-live="polite"
            ></div>

            <div class="cms-editor">
                <iframe
                    id="preview"
                    title="Sanctuario Website"
                    src="draft.php?cms_edit=1"
                ></iframe>
            </div>
        </section>
    </main>

    <div id="editModal" class="cms-overlay" hidden>
        <div
            class="cms-dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="editTitle"
        >
            <div class="cms-dialog-header">
                <h2 id="editTitle">Edit Section</h2>
                <p>Update content, then save your changes.</p>
            </div>

            <form id="editForm">
                <div id="editFields"></div>

                <div class="cms-dialog-actions">
                    <button id="editCancel" type="button">
                        Cancel
                    </button>

                    <button class="cms-publish" type="submit">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="slidesModal" class="cms-overlay" hidden>
        <div
            class="cms-dialog cms-slides-dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="slidesTitle"
        >
            <div class="cms-dialog-header">
                <h2 id="slidesTitle">Manage Slideshow</h2>
                <p>
                    Edit each slide's image and text,
                    then save all slides together.
                </p>
            </div>

            <form id="slidesForm">
                <div id="slidesFields"></div>

                <div class="cms-dialog-actions">
                    <button id="slidesCancel" type="button">
                        Cancel
                    </button>

                    <button class="cms-publish" type="submit">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="publishConfirm" class="cms-overlay" hidden>
        <div
            class="cms-dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="publishTitle"
        >
            <div class="cms-dialog-header">
                <h2 id="publishTitle">Publish Website?</h2>

                <p>
                    Your latest edits will be saved and published
                    to the public website.
                </p>
            </div>

            <div class="cms-dialog-actions">
                <button id="publishNo" type="button">
                    Cancel
                </button>

                <button
                    id="publishYes"
                    class="cms-publish"
                    type="button"
                >
                    Yes, Publish
                </button>
            </div>
        </div>
    </div>


    <div id="cmsHistoryOverlay" class="cms-overlay" hidden>
        <div class="cms-dialog cms-history-dialog" role="dialog" aria-modal="true" aria-labelledby="cmsHistoryTitle">
            <div class="cms-history-heading">
                <div>
                    <h2 id="cmsHistoryTitle">Website Change History</h2>
                    <p>Saved drafts and published versions. Restoring creates a draft, not a live publication.</p>
                </div>
                <button id="cmsHistoryClose" type="button">Close</button>
            </div>
            <div id="cmsHistoryList" class="cms-history-list"></div>
            <div id="cmsHistoryDetails" class="cms-history-details" hidden></div>
        </div>
    </div>

    <script>
        window.CMS_TOKEN =
            <?= json_encode($_SESSION['cms_csrf']) ?>;

        window.CMS_HAS_DRAFT =
            <?= is_file(__DIR__ . '/drafts/index.html') ? 'true' : 'false' ?>;
    </script>

    <script src="cms.js"></script>
</body>
</html>