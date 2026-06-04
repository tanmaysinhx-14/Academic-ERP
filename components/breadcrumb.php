<div class="text-primary-emphasis bg-primary-subtle">
  <div class="container">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb ff-inter">

        <?php if(isset($breadcrumb_url_1)): ?>
          <li class="breadcrumb-item">
            <a class="text-decoration-none"
                href="<?php echo escapeOutput($breadcrumb_url_1 ?? null); ?>">
              <?php echo escapeOutput($breadcrumb_title_1 ?? null); ?>
            </a>
          </li>
        <?php endif; ?>

        <?php if(isset($breadcrumb_url_2)): ?>
          <li class="breadcrumb-item">
            <a class="text-decoration-none"
                href="<?php echo escapeOutput($breadcrumb_url_2 ?? null); ?>">
              <?php echo escapeOutput($breadcrumb_title_2 ?? null); ?>
            </a>
          </li>
        <?php endif; ?>

        <?php if(isset($breadcrumb_title_active)): ?>
          <li class="breadcrumb-item active" aria-current="page">
            <span>
              <?php echo escapeOutput($breadcrumb_title_active ?? null); ?>
            </span>
          </li>
        <?php endif; ?>

      </ol>
    </nav>
  </div>
</div>