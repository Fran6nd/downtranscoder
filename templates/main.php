<?php
/**
 * Main page template for DownTranscoder kanban board
 */

// Load scripts and styles - Nextcloud will handle this automatically
script('downtranscoder', 'downtranscoder-main');
style('downtranscoder', 'main');
?>

<div id="content" class="app-downtranscoder">
    <div id="app-content">
        <div id="app-content-wrapper">
            <!-- Kanban app will render here -->
            <div id="downtranscoder-app"></div>
        </div>
    </div>
</div>
