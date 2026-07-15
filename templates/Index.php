<?php
extract($_);
?>
<div id="app-vapor-wrapper">
    <a href="#" id="vapor-menu-toggle" class="vapor-hamburger" title="<?php p($l->t('Toggle navigation')); ?>">
        <span class="hamburger-icon">☰</span>
    </a>
    <?php print_unescaped($this->inc('Navigation'));?>
    <?php print_unescaped($this->inc('Content'));?>
    <div id="app-settings-data" data-search-sites=<?php print $search_sites;?> data-settings='<?php print($settings);?>' ></div>
</div>
