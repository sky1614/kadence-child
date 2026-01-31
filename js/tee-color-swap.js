jQuery(function ($) {
  function currentColor() {
    return $('[name="attribute_pa_color"]').val();
  }

  function swap(slug) {
    if (!slug || !TEE_SWAP.map[slug]) return;
    $('#tee-base-image').attr('src', TEE_SWAP.map[slug]);
  }

  $('form.variations_form')
    .on('found_variation change', function () {
      swap(currentColor());
    });

  swap(currentColor());
});

