jQuery(document).ready(function(a){a(".wr-single").on("click",".wr-truncated-show",function(b){b.preventDefault(),a(this).prev(".wr-truncated").slideDown("fast"),a(this).remove()});var b=a(".wr-carousel");jQuery().slick&&b.length&&b.fadeIn(400).slick({infinite:!0,slidesToShow:3,slidesToScroll:1,dots:!0,arrows:!0,adaptiveHeight:!0,autoplay:!0,autoplaySpeed:5e3,lazyLoad:"ondemand",responsive:[{breakpoint:992,settings:{slidesToShow:2,slidesToScroll:2}},{breakpoint:768,settings:{slidesToShow:1,slidesToScroll:1}}]})});