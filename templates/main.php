<?php
/**
 * Main page template for DownTranscoder kanban board
 */

script('downtranscoder', 'downtranscoder-main');
style('downtranscoder', 'main');
?>

<div id="app-downtranscoder">
    <div id="app-navigation">
        <ul>
            <li>
                <a href="#" class="nav-item active">
                    <span class="icon-category-multimedia"></span>
                    <span>Media Board</span>
                </a>
            </li>
        </ul>
        <div id="app-settings">
            <div id="app-settings-header">
                <button class="settings-button"
                        data-apps-slide-toggle="#app-settings-content">
                    Settings
                </button>
            </div>
            <div id="app-settings-content">
                <h3>Scan Settings</h3>
                <p>
                    <label for="min-file-size">Minimum file size (MB):</label>
                    <input type="number" id="min-file-size" value="100" min="1" />
                </p>
                <p>
                    <label>
                        <input type="checkbox" id="auto-scan" />
                        Enable automatic scanning
                    </label>
                </p>
            </div>
        </div>
    </div>

    <div id="app-content">
        <div id="app-content-wrapper">
            <!-- Vue app will mount here -->
            <div id="downtranscoder-app"></div>
        </div>
    </div>
</div>
