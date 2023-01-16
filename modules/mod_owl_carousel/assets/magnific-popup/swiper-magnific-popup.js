(function() {
  /* Define a variável que dá swipe no lightbox */
  var magnificPopup = $.magnificPopup.instance;

  /* Carrega a função quando clica no lightbox (senão não pega a classe utilizada) */
  $("a.image-lightbox").click(function(e) {

    /* Espera carregar o lightbox */
    setTimeout(function() {
        /* Swipe para a esquerda - Próximo */
        $(".mfp-container").swipe( {
          swipeLeft:function(event, direction, distance, duration, fingerCount) {
            console.log("swipe right");
            magnificPopup.next();
          },

        /* Swipe para a direita - Anterior */
        swipeRight:function(event, direction, distance, duration, fingerCount) {
          console.log("swipe left");
          magnificPopup.prev();
        },
        });
    }, 500);
  });

}).call(this);