<?php
/**
 * includes/footer.php - shared bottom of every public page.
 * Optional: $extraScripts = ['js/booking.js'] to load page-specific JavaScript.
 */
$extraScripts = $extraScripts ?? [];
?>
  </main>

  <footer class="footer">
    <div class="container footer-grid">
      <div>
        <a class="brand footer-brand" href="<?= e(url('index.php')) ?>">
          <span class="brand-mark">🎈</span>
          <span><strong>Caroline's <em>Party Helper</em></strong><small>YOUR PARTY, MY HELPING HANDS</small></span>
        </a>
        <p>Helping make your celebrations a little easier.</p>
      </div>
      <div>
        <h4>Quick Links</h4>
        <a href="<?= e(url('index.php')) ?>">Home</a>
        <a href="<?= e(url('services.php')) ?>">Services</a>
        <a href="<?= e(url('about.php')) ?>">About</a>
        <a href="<?= e(url('gallery.php')) ?>">Gallery</a>
      </div>
      <div>
        <h4>Explore</h4>
        <a href="<?= e(url('booking.php')) ?>">Request a Booking</a>
        <a href="<?= e(url('contact.php')) ?>">Contact</a>
      </div>
      <div>
        <h4>Get in Touch</h4>
        <a href="tel:+16319979504">(631) 997-9504</a>
        <a href="mailto:carolinespartyhelper@gmail.com">carolinespartyhelper@gmail.com</a>
      </div>
    </div>
    <div class="container copyright">© <span id="year"><?= date('Y') ?></span> Caroline's Party Helper. All rights reserved.</div>
  </footer>

  <script src="<?= e(asset('js/main.js')) ?>"></script>
  <?php foreach ($extraScripts as $script): ?>
  <script src="<?= e(asset($script)) ?>"></script>
  <?php endforeach; ?>
</body>
</html>
