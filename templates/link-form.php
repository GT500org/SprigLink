<?php
$mode = $mode ?? 'create';
$isEdit = $mode === 'edit';
$preset = (string) ($values['expires_preset'] ?? 'never');
?>
<div class="field span-2">
  <label for="url">Destination URL</label>
  <input id="url" name="url" type="text" inputmode="url" required maxlength="4000" placeholder="https://example.com/very/long/path" value="<?= e($values['url']) ?>">
</div>
<div class="field">
  <label for="title">Title <span class="opt">optional</span></label>
  <input id="title" name="title" maxlength="200" placeholder="Shown in the admin and on preview pages" value="<?= e($values['title']) ?>">
</div>
<div class="field">
  <label for="code">Short code <?php if (!$isEdit): ?><span class="opt">blank = random</span><?php endif ?></label>
  <div class="inline">
    <input id="code" name="code" maxlength="64" autocapitalize="off" spellcheck="false" placeholder="<?= $isEdit ? '' : 'auto' ?>" value="<?= e($values['code']) ?>" pattern="[A-Za-z0-9_-]*">
    <button class="btn btn-ghost" type="button" data-suggest="<?= (int) code_length() ?>">Suggest</button>
  </div>
</div>
<div class="field">
  <label for="expires_preset">Expires</label>
  <select id="expires_preset" name="expires_preset" data-expiry-preset>
    <?php foreach (['never' => 'Never', '1h' => 'In 1 hour', '1d' => 'In 1 day', '7d' => 'In 7 days', '30d' => 'In 30 days', '90d' => 'In 90 days', 'custom' => 'Custom date'] as $val => $label): ?>
      <option value="<?= e($val) ?>"<?= sel($preset, $val) ?>><?= e($label) ?></option>
    <?php endforeach ?>
  </select>
</div>
<div class="field" data-expiry-custom <?= $preset === 'custom' ? '' : 'hidden' ?>>
  <label for="expires_at">Expiry date <span class="opt"><?= e(app_tz()->getName()) ?></span></label>
  <input id="expires_at" name="expires_at" type="datetime-local" value="<?= e($values['expires_at']) ?>">
</div>
<div class="field">
  <label for="click_limit">Click limit <span class="opt">blank = none</span></label>
  <input id="click_limit" name="click_limit" inputmode="numeric" maxlength="9" placeholder="500" value="<?= e($values['click_limit']) ?>">
</div>
<div class="field">
  <label for="password">Link password <span class="opt"><?= !empty($values['has_password']) ? 'already set' : 'optional' ?></span></label>
  <input id="password" name="password" type="password" maxlength="200" autocomplete="new-password" placeholder="<?= !empty($values['has_password']) ? 'Leave blank to keep it' : 'None' ?>">
</div>
<div class="field">
  <label for="redirect_mode">When opened</label>
  <select id="redirect_mode" name="redirect_mode">
    <option value="instant"<?= sel((string) $values['redirect_mode'], 'instant') ?>>Redirect immediately</option>
    <option value="preview"<?= sel((string) $values['redirect_mode'], 'preview') ?>>Show the destination first</option>
  </select>
</div>
<div class="field">
  <label for="status_code">Redirect type</label>
  <select id="status_code" name="status_code">
    <option value="302"<?= sel((string) $values['status_code'], '302') ?>>302 temporary (keeps stats accurate)</option>
    <option value="301"<?= sel((string) $values['status_code'], '301') ?>>301 permanent (browsers may cache it)</option>
  </select>
</div>
<?php if ($isEdit): ?>
  <div class="field span-2 checks">
    <label class="check"><input type="checkbox" name="is_active" value="1"<?= chk(!empty($values['is_active'])) ?>> Link is on</label>
    <label class="check"><input type="checkbox" name="keep_alias" value="1"<?= chk(!empty($values['keep_alias'])) ?>> If the code changes, keep the old one working</label>
    <?php if (!empty($values['has_password'])): ?>
      <label class="check"><input type="checkbox" name="remove_password" value="1"<?= chk(!empty($values['remove_password'])) ?>> Remove the password</label>
    <?php endif ?>
  </div>
<?php endif ?>
<div class="field span-2 checks">
  <label class="check"><input type="checkbox" name="is_private" value="1"<?= chk(!empty($values['is_private'])) ?>> Private — keep this link off the homepage</label>
</div>
<div class="field span-2">
  <label for="notes">Notes <span class="opt">only visible to you</span></label>
  <textarea id="notes" name="notes" maxlength="2000" rows="3"><?= e($values['notes']) ?></textarea>
</div>
