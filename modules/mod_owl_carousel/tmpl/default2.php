<?php
// no direct access
defined('_JEXEC') or die;

	
$document->addCustomTag('
<style type="text/css">

.owl_carousel_'.$owl_id.' {
	max-width: '.$owl_width_block.'px;
}

</style>');
?>


<script type="text/javascript">
jQuery(document).ready(function(){
	jQuery("#owl-example-<?php echo $owl_id;?>").owlCarousel({
		items : <?php echo $owl_items; ?>,		

		navigation : <?php echo $owl_navigation; ?>,
		pagination : <?php echo $owl_pagination; ?>,
		
		paginationNumbers : <?php echo $owl_paginationnumbers; ?>	
		
		
	});
	
  
});


</script>

<div class="owl_carousel<?php echo $owl_id; ?> <?php echo $moduleclass_sfx ?>">	
	<div id="owl-example-<?php echo $owl_id; ?>" class="owl-carousel owl-theme" >	
		<?php	
		for($n=0;$n < count($img);$n++) {			
			if( $img[$n] != '') {		
				if ($url[$n] != '') {
					echo '<div class="owl-item">';
					echo '<a href="'.$url[$n].'" target="'.$target[$n].'"><img src="'.$img[$n].'" alt="'.$alt[$n].'" /></a>';
					if ($html[$n] != '') {
						echo '<div class="owl-item-html">'.$html[$n].'</div>';
					}
					echo '</div>';
					
				
				} else {
						echo '<div class="testimonials-section2"><div class="owl-item">';
						
						if ($html[$n] != '') {
							echo '<div class="owl-item-html">'.$html[$n].'</div>';
						}
						echo '</div></div>';
					}

			}
		}	
		?>
	</div>	
	
	<div style="clear:both;"></div>
</div>

