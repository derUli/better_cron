<?php
$cronjobs = BetterCron::getAllCronjobs();
?>
<table class="tablesorter">
    <thead>
        <tr>
            <th><?php translate("name"); ?></th>
            <th><?php translate("last_run"); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($cronjobs as $name => $last_run) { ?>
            <tr>
                <td><?php esc($name); ?></td>
                <td><?php esc(strftime("%x %X", $last_run)); ?></td>
            </tr>
        <?php } ?>
    </tbody>
</table>