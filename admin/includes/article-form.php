<?php if (! empty($article->errors)) : ?>
<ul>
  <?php foreach ($article->errors as $error) : ?>
  <li><?= $error ?>
  </li>
  <?php endforeach; ?>
</ul>
<?php endif; ?>

<form method="post" id="formArticle">

  <div class="form-group">
    <label for="title">Title</label>
    <input name="title" id="title" class="form-control" placeholder="Article title"
      value="<?= htmlspecialchars($article->title); ?>">
  </div>

  <div class="form-group">
    <label for="content">Content</label>
    <textarea name="content" rows="4" cols="40" id="content" class="form-control"
      placeholder="Article content"><?= htmlspecialchars($article->content); ?></textarea>
  </div>

  <div class="form-group">
    <label for="published_at">Publication date and time</label>
    <input name="published_at" id="published_at" class="form-control"
      value="<?= htmlspecialchars($article->published_at); ?>">
  </div>

  <fieldset>
    <legend>Categories</legend>

    <?php foreach ($categories as $category): ?>
      <div class="form-check">
        <input 
          type="checkbox" 
          name="category[]" 
          value="<?= $category['id']; ?>" 
          id="category<?= $category['id']; ?>"
          class="form-check-input"
          <?php if (in_array($category['id'], $category_ids)): ?>
            checked
          <?php endif; ?>
        />
        <label 
          for="category<?= $category['id']; ?>"
          class="form-check-label"
        >
          <?= htmlspecialchars($category['name']); ?>
        </label>
      </div>
    <?php endforeach; ?>

  </fieldset>

  <button class="btn btn-primary">Save</button>

</form>