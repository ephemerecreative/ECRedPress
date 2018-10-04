<h1><?php echo __("ECRedPress", "ecrp") ?></h1>

<h2><?php echo __("Clear Cache", "ecrp") ?></h2>

<form method="get" action="?ecrp_action=clear-cache">
    <?php submit_button("Clear Cache", "primary", "ecrp_action") ?>
</form>

<h2><?php echo __("ECRP Config", "ecrp") ?></h2>

<table>
    <thead>
    <tr>
        <td><b><?php echo __("Setting", "ecrp") ?></b></td>
        <td><b><?php echo __("Value", "ecrp") ?></b></td>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($ecrp->public_config() as $item => $value): ?>
        <tr>
            <td><b><?php echo $item ?></b></td>
            <td><?php print_r($value) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>