function update_account_number_field_ui() {
  const bank = jQuery('#woocommerce_sepay_bank_select').val()

  if (['ocb', 'msb', 'kienlongbank'].includes(bank)) {
      jQuery('label[for=woocommerce_sepay_bank_account_number]').html('Số VA');
      jQuery('input[name=woocommerce_sepay_bank_account_number]').parent().find('.help-text').html('Vui lòng điền chính xác <strong>số VA</strong> để nhận được biến động giao dịch.')

  } else {
      jQuery('label[for=woocommerce_sepay_bank_account_number]').html('Số tài khoản');
      jQuery('input[name=woocommerce_sepay_bank_account_number]').parent().find('.help-text').html('Vui lòng điền chính xác <strong>số tài khoản ngân hàng</strong> để nhận được biến động giao dịch.')
  }
}
function check_url_site() {
  let base_url = jQuery("#woocommerce_sepay_url_root").val();
  let url = base_url + "/wp-json/sepay-gateway/v1/add-payment";
  
  if(!base_url){
    jQuery("#content-render").css("display","none");
    return
  }else{
    jQuery("#content-render").css("display","block")
  }

  jQuery.ajax({
    url: url,
    type: "POST",
    contentType: "application/json",
    success: function (response) {
      // console.log("result: " + response);
      jQuery("#site_url").html(url);
    },
    error: function (xhr, status, error) {
      // console.error("Exception:", error);
      jQuery("#site_url").html(
        base_url + "/?rest_route=/sepay-gateway/v1/add-payment"
      );
    },
  });
}
jQuery('document').ready(() => {
  jQuery('input[name=woocommerce_sepay_bank_account_number]').parent().append('<div class="help-text" style="box-sizing: border-box; color: #856404; background-color: #fff3cd; border-color: #ffeeba; padding: .75rem 1.25rem; border-radius: .25rem; border: 1px solid transparent; margin-top: 0.5rem; width: 400px;"></div>')
  update_account_number_field_ui()

  jQuery('#woocommerce_sepay_bank_select').on('change', (event) => {
    update_account_number_field_ui()
  })
  check_url_site();
})
