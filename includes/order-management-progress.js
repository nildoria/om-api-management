jQuery(document).ready(function ($) {
  $("#startProcessing").on("click", function () {
    $("#progressWrapper").show();
    $("#progressStatus").show();
    processOrderBatch();
  });

  function processOrderBatch() {
    $.ajax({
      url: orderManagementVars.ajaxUrl,
      method: "POST",
      data: {
        action: "process_order_batch",
      },
      success: function (response) {
        if (response.success) {
          let processed = response.data.processed;
          let remaining = response.data.remaining_orders;

          // Update the progress bar and status
          let totalOrders = processed + remaining;
          let progress = ((totalOrders - remaining) / totalOrders) * 100;
          $("#progressBar").css("width", progress + "%");
          $("#processedCount").text(totalOrders - remaining);

          if (remaining > 0) {
            // Continue processing the next batch
            processOrderBatch();
          } else {
            // All orders processed
            $("#progressStatus").text("All orders processed.");
          }
        } else {
          $("#progressStatus").text(
            response.data || "Error processing orders."
          );
        }
      },
      error: function (xhr, status, error) {
        $("#progressStatus").text("An error occurred: " + error);
      },
    });
  }
});
