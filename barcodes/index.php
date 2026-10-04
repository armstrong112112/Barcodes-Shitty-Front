<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/access.inc.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db.inc.php';

if (!userIsLoggedIn())
{
include 'login.html.php';
exit();
}


if (!userHasRole('Администратор'))
{
$error = 'Отказано в доступе. У Вас нет привилегии Пользователя';
include 'accessdenied.html.php';
exit();
}



$email = $_SESSION['email'];




$sql = "SELECT * FROM users where email = :email --";
$statement = $pdo->prepare($sql);
$statement->bindValue(':email', $email);
$statement->execute();
$user = $statement->fetchAll();
foreach ($user as $u) { $Ename = $u['email']; }


?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Barcodes</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link href="img/favicon.ico" rel="shortcut icon" type="image/x-icon" />
	<link rel="stylesheet" href="css/style.css">	
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
	

	<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
	<script src="https://code.jquery.com/ui/1.14.1/jquery-ui.js"></script>
	<link rel="stylesheet" href="https://code.jquery.com/ui/1.14.1/themes/base/jquery-ui.css">

	<script>
	$( function() {
		$( "#datepicker" ).datepicker();
	} );
	</script>


</head>
<body>

<!-- HEADER -->
<div id='header' class="desctop">
	<nav>
		<ul class="topmenu">
			<li><a href="">Profile</a></li>
			<li><a href="">Wallet</a></li>
			<li><a href="">Barcodes</a></li>
			<li><a href="">MRZ</a></li>
			<li><a href="">Articles</a></li>
			<li><a href="">Discussion</a></li>
			

			<li><a  class="submenu-link">More</a>
				<ul class="submenu">
					<li><a href="">More 1</a></li>
					<li><a href="">More 2</a></li>
					<li><a href="">More 3</a></li>
				</ul>

			<li><img id="lang" src='img/lang.png' />
				<ul class="submenu">
					<li><a href="">Russian</a></li>
					<li><a href="">English</a></li>
					<li><a href="">Chines</a></li>
				</ul>
			
			<li>
				<img class="avatar" src='img/user.png' />
			</li>
			<li>
				<div style="padding:13px 25px;line-height:24px;"><span style="font-weight:bold;font-size:22px;">Hello <?php echo $Ename; ?></span><br/><span style="font-size:13px;">Welcome back!</span></div>
			</li>
			
		</ul>
	</nav>
</div>


<!-- HEADER MOBILE -->
<div id='header' class="mobile">
	<nav>
		<ul class="topmenu">
			<li id="menu-batton"><img id="smenai" src="img/menu.png" /></li>
			<li class="menu-hide"><a>Profile</a></li>
			<li class="menu-hide"><a>Wallet</a></li>
			<li class="menu-hide"><a>Barcodes</a></li>
			<li class="menu-hide"><a>MRZ</a></li>
			<li class="menu-hide"><a>Articles</a></li>
			<li class="menu-hide"><a>Discussion</a></li>

			<li class="menu-hide"><a  class="submenu-link">More</a>
				<ul class="submenu">
					<li><a>More 1</a></li>
					<li><a>More 2</a></li>
					<li><a>More 3</a></li>
				</ul>

			<li class="menu-hide"><img id="lang" src='img/lang.png' />
				<ul class="submenu langSub">
					<li><a>Russian</a></li>
					<li><a>English</a></li>
					<li><a>Chines</a></li>
				</ul>
			<!--
			<li class="menu-hide">
				<img class="avatar" src='img/user.png' />
			</li>
			<li class="menu-hide">
				<div style="padding:13px 25px;line-height:24px;"><span style="font-weight:bold;font-size:22px;">Hello User</span><br/><span style="font-size:13px;">Welcome back!</span></div>
			</li>
			-->
		</ul>
	</nav>
</div>


<!-- maps -->
<div id="maps">

	<div class="main">
		<h1 class='main-text'>AI BARCODES GENERATED</h1>
		<h2 class='main-dop-text'>
			Click on the state on the map
		</br>
			to start first AI-powered barcode journey!
		</h2>
	</div>
	
	
	<div >
		<div class='maps-left'>
			<img id='USA' src="img/unitedState.png" />
			<button class="btn confirmBtn" value="USA">United States</button>
		</div>
		
		<div class='maps-right'>
			<img id='CAN' src="img/canada.png" />
			<button class="btn confirmBtn" value="CAN">Canada</button>
		</div>
	</div>

</div>


<div id="mapsNext">

	<div class="main2">
		<h1 class='main-text2'>AI BARCODES GENERATED</h1>
		<h2 class='main-dop-text2'>
			Click on the state on the map
		</br>
			to start first AI-powered barcode journey!
		</h2>
	</div>

	<div class='maps-chose'>
		<img class="smena"  src="img/unitedState.png" />

		<div class="select-box">
			<label for="select-box1" class="label select-box1"><span class="label-desc">Choose Your State</span> </label>

			<div id="result">
				
			</div>

		</div>
		
		<div id='DDB' class="select-box">

		</div>

		<div id='QQQ' class="select-box">

		</div>

		<button disabled class="btn2" id="next_step" value="US">Confirm</button>
	</div>

</div>


<div id="checkpoint">

	<div class="main2">
		<h1 class='main-text2'>AI BARCODES GENERATED</h1>
		<h2 class='main-dop-text2'>
			Check if the design reflects the ID you're actually creating to prevent misclicks or mistakes due to confusion.
		</h2>
	</div>

	<div class='maps-chose'>
		<img class='smena' src="" />
		
		<img class="driver" src="img/driver_pack.png" />
		
		<p class="choosenV" >Choosen Version:</p>
		<p class="choosen" >State: <span class="innerState" ></span></p>
		<p class="choosen" >Rev: <span class="innerDate" ></span> (0402)</p>
		<p class="choosen" >General Version: 04</p>
		<p class="choosen" >Jurisdiction version: 02</p>
		
		<div class="select-box">
			<label for="select-box4" class="label select-box4"><span class="label-desc">Select Generation Mode</span> </label>
			<select name='mode' id="select-box4" class="select">
				<option value="-"> - </option>
				<option value="Light">Light</option>
				<option value="Regular">Regular</option>
				<option value="Advanced">Advanced</option>
			</select>
		</div>
		
		<button disabled id="next" class="btn2" value="next" style="margin-bottom:10px;">Continue</button>
		<button class="btn2" >Tips for This Revision</button>
	</div>

</div>





<div id="genMode">

	<div class="main2">
		<h1 class='main-text2'>AI BARCODES GENERATED</h1>
		<h2 class='main-space mode_take'></h2>
	</div>

	<form method='POST' id='GenBar'>
	<div id="InputData" class='maps-chose'>
		 
		
	</div>

		    
		<input type="checkbox" id="css-modal-checkbox" />
		<div class="cmc">
			<div class="cmt">
				<label for="css-modal-checkbox" class="css-modal-close"></label>
				<p id="modal-text">What's the Deal About Names?</p>
				<video id="videos" src="video/name.mp4" controls>
				</video>
				<button  class="btn2" >Got it! Continue!</button>
			</div>
			
		</div>

	<script id='js_script'></script>
	<script id='js_script2'></script>
	<script id='js_script3'></script>
	<script id='js_script4'></script>
	<script id='js_script5'></script>
	
	<div id="vivod-text" class='maps-chose'></div>
	<div id="vivod" class='maps-chose'></div>
	<div id="vivodInv" class='maps-chose'></div>
	<div id="table" class='maps-chose'></div>

	</div>

	</form>

</div>



</body>

<script>
/*
$(".main-text").on("click" , function() {
	var hello = $("#datepicker").val();
	var dt_to = $.datepicker.formatDate('mmddyy', new Date());
});
*/
var dt_to = $.datepicker.formatDate('mmddyy', new Date());
</script>




<script>
$(".confirmBtn").on("click" , function() {
	$( "#maps" ).fadeOut(500);
	setTimeout(function(){$( "#mapsNext" ).fadeIn(500);}, 600);
	
	let STATE_S = $(this).val(); // USA OR CAN
	if ( STATE_S == 'CAN' )
	{
		$( ".smena" ).attr('src', 'img/canada.png');
	}
	else 
	{
		$( ".smena" ).attr('src', 'img/unitedState.png');
	}
		$( "#next_step" ).attr('value', STATE_S);

		let strana = $(this).val();
		$.ajax({
			url: 'stateGet.php',
			method: 'POST',
			data: {chooseCountry: strana },
			success: function(data){
				$('#result').html(data.desc);

				$("select").on("click" , function() { $(this).parent(".select-box").toggleClass("open"); });
				$(document).mouseup(function (e) { var container = $(".select-box"); if (container.has(e.target).length === 0) { container.removeClass("open");} });
				$("select").on("change" , function() { var selection = $(this).find("option:selected").text(), labelFor = $(this).attr("id"), label = $("[for='" + labelFor + "']"); label.find(".label-desc").html(selection); });

				$("select[name=DAJ]").change( function() {
					let stateVal = $("select[name=DAJ] option:selected").val();
					$.ajax({
								url: 'stateGet.php',
								method: 'POST',
								data: {state: stateVal, country: strana },
								success: function(data){
									$('#DDB').html(data.desc);
									$('#DDB').css('display', 'block');

									$('#QQQ').html(data.desc2);
									$('#QQQ').css('display', 'block');

									$('.driver').attr('src', 'img/state/'+stateVal+'.jpg');
									
									$("select").on("click" , function() { $(this).parent(".select-box").toggleClass("open"); });
									$(document).mouseup(function (e) { var container = $(".select-box"); if (container.has(e.target).length === 0) { container.removeClass("open");} });
									$("select").on("change" , function() { var selection = $(this).find("option:selected").text(), labelFor = $(this).attr("id"), label = $("[for='" + labelFor + "']"); label.find(".label-desc").html(selection); });

									$("select[name=QQQ]").on("change" , function() { $('#next_step').attr("disabled", false); });
									

									$("#next_step").on("click" , function() {

										$( "#mapsNext" ).fadeOut(500);
										let DDB = $("select[name=DDB]").val();
										let DAJ = $("select[name=DAJ]").val();
										let state = $("select[name=DAJ] option:selected").attr('atribute');
										let QQQ = $("select[name=QQQ] option:selected").val();

										$('.innerState').html(state);
										$('.innerDate').html(DDB);

										setTimeout(function(){$( "#checkpoint" ).fadeIn(500);}, 600);
										$("select[name=mode]").on("change" , function() { $('#next').attr("disabled", false); });


										$("#next").on("click" , function() {
											$( "#checkpoint" ).fadeOut(500);





											let mode = $("select[name=mode]").val();
											if ( mode == 'Light' )
											{

												setTimeout(function(){$( "#genMode" ).fadeIn(500);}, 600);
												$(".mode_take").html(mode+' Mode');
												
												$.ajax({
													url: 'stateGet.php',
													method: 'POST',
													data: {DAJ: DAJ, DDB: DDB, QQQ: QQQ, country: strana, modeBar: mode },
													success: function(data){
														$('#InputData').html(data.barcodes);


														$("#GenerateLight").on("click" , function() {

															$('#GenBar').submit(function(event){
																event.preventDefault();

																$.ajax({
																	url: 'genBar.php',
																	method: 'POST',
																	data: $('#GenBar').serialize(),
																	success: function(data){
																		
																		$('#vivod-text').html(data.endFor);
																		$('#vivod').html(data.images);
																		$('#vivodInv').html(data.images2);
																		$('#table').html(data.table);
																		
																		$('#GenerateLight').attr("disabled", true);
																	}
																});

															});

														});
												}});



											}
											else if ( mode == 'Regular' )
											{

												setTimeout(function(){$( "#genMode" ).fadeIn(500);}, 600);
												$(".mode_take").html(mode+' Mode');
												
												$.ajax({
													url: 'stateGet.php',
													method: 'POST',
													data: {DAJ: DAJ, DDB: DDB, QQQ: QQQ, country: strana, modeBar: mode },
													success: function(data){
														$('#InputData').html(data.barcodes);
														$('#js_script').html(data.js_DAQ);
														$('#js_script2').html(data.js_DBD);
														$('#js_script3').html(data.js_DBA);
														$('#js_script4').html(data.js_DCK);
														$('#js_script5').html(data.js_DCF);
														
														$("#GenerateAdvanced").on("click" , function() {

															$('#GenBar').submit(function(event){
																event.preventDefault();

																$.ajax({
																	url: 'stateGet.php',
																	method: 'POST',
																	data: $('#GenBar').serialize(),
																	success: function(data){

																		$('#vivod-text').html(data.endFor);
																		$('#vivod').html(data.images);
																		$('#vivodInv').html(data.images2);
																		$('#table').html(data.table);
																		
																		$('#GenerateAdvanced').attr("disabled", true);
																	}
																});

															});

														});


												}});

											}
											else if ( mode == 'Advanced' )
											{
												setTimeout(function(){$( "#genMode" ).fadeIn(500);}, 600);
												$(".mode_take").html(mode+' Mode');
												
												$.ajax({
													url: 'stateGet.php',
													method: 'POST',
													data: {DAJ: DAJ, DDB: DDB, QQQ: QQQ, country: strana, modeBar: mode },
													success: function(data){
														$('#InputData').html(data.barcodes);


														$("#GenerateAdvanced").on("click" , function() {

															$('#GenBar').submit(function(event){
																event.preventDefault();

																$.ajax({
																	url: 'stateGet.php',
																	method: 'POST',
																	data: $('#GenBar').serialize(),
																	success: function(data){

																		$('#vivod-text').html(data.endFor);
																		$('#vivod').html(data.images);
																		$('#vivodInv').html(data.images2);
																		$('#table').html(data.table);

																		$('#GenerateAdvanced').attr("disabled", true);
																	}
																});

															});

														});


												}});														

											}
												
										});

									});

								},
							error: function(XMLHttpRequest, textStatus, errorThrown) {
								alert('Сообщите в техподдержку об ошибке');
							}
						});
				});

				
			},
			error: function(XMLHttpRequest, textStatus, errorThrown) {
			alert('Сообщите в техподдержку об ошибке');
			}
		});

});

</script>

<script>
$('.open-close').on('click' , function() {
	if ( $('.open-close').hasClass('open') )
	{
		$('.open-close').html('Open Full PDF417 Barcode Data');
		$('.open-close').removeClass('open');
		$('.open-close').addClass('close');
		$('#scroll').toggle();
	}
	else if ( $('.open-close').hasClass('close') )
	{
		$('.open-close').html('Hide Full PDF417 Barcode Data');
		$('.open-close').removeClass('close');
		$('.open-close').addClass('open');
		$('#scroll').toggle();
	}
});
</script>
	

<script>
// CHECKBOX
$(window).keyup(function(e){
	var target = $('.checkbox-ios input:focus');
	if (e.keyCode == 9 && $(target).length){
		$(target).parent().addClass('focused');
	}
});
 
$('.checkbox-ios input').focusout(function(){
	$(this).parent().removeClass('focused');
});
</script>

<script>
// SELECT
$("select").on("click" , function() {
  $(this).parent(".select-box").toggleClass("open");
});

$(document).mouseup(function (e)
{
    var container = $(".select-box");
    if (container.has(e.target).length === 0)
    {
        container.removeClass("open");
    }
});

$("select").on("change" , function() {
  var selection = $(this).find("option:selected").text(),
      labelFor = $(this).attr("id"),
      label = $("[for='" + labelFor + "']");
	  label.find(".label-desc").html(selection);
});
</script>

<script>
// MENU MOBILE
$("#menu-batton").on("click" , function() {

let nammes = $("#smenai").attr('src');
if (nammes == 'img/menu.png') 
{
	$("#smenai").fadeOut();

	setTimeout(function(){
		$(".mobile").css('height','100vh');
		$("#smenai").attr('src', 'img/close.png');
		$("#smenai").css('width', '35px').css('height', '35px').css('margin-top', '7px');
		$("#smenai").fadeIn();
		$(".menu-hide").fadeIn();
	}, 1000);
}
else if (nammes == 'img/close.png')
{
	$("#smenai").fadeOut();
	$(".menu-hide").fadeOut();

	setTimeout(function(){
		$(".mobile").css('height','');
		$("#smenai").css('width', '50px').css('height', '50px').css('margin-top', '0');
		$("#smenai").attr('src', 'img/menu.png');
	
		$(".menu-hide").fadeOut();
		$("#smenai").fadeIn();

	}, 1000);
}
});
</script>

<script>
// Всплывающее меню
$(".tooltip").on("click" , function() {

let nammes = $(this).attr('name');

if ( nammes == 'nameTool' )
{
	$("#modal-text").html("What's the Deal About Names?");
	$("#videos").attr("src", "video/name.mp4");
	$('select[name=statePerson] option:contains("FL")').prop('selected', true);
}
else if ( nammes == 'AddressTool' )
{
	$("#modal-text").html("What's the Deal About Address?");
	$("#videos").attr("src", "video/address.mp4");
}
else if ( nammes == 'PhysicalDescriptionTool' )
{
	$("#modal-text").html("What's the Deal About Physical Description");
	$("#videos").attr("src", "video/physical.mp4");
}
else if ( nammes == 'SpecialMarks' )
{
	$("#modal-text").html("What's the Deal About Special Marks?");
	$("#videos").attr("src", "video/marks.mp4");
}
else if ( nammes == 'Dates' )
{
	$("#modal-text").html("What's the Deal About Dates on the DL?");
	$("#videos").attr("src", "video/dates.mp4");
}

});
</script>
</html>



<!--
		<img class="driver" src="img/driver_pack.png" />
		
		<p style="font-weight:bold;color:white;text-align:left;margin: 20px 65px;font-size:30px;">Choosen Version:</p>
		<p style="color:white;text-align:left;margin:0 65px;font-size:22px;">State: <span class="innerState" ></span></p>
		<p style="color:white;text-align:left;margin:0 65px;font-size:22px;">Rev: <span class="innerDate" ></span> (0402)</p>
		<p style="color:white;text-align:left;margin:0 65px;font-size:22px;">General Version: 04</p>
		<p style="color:white;text-align:left;margin:0 65px;font-size:22px;">Jurisdiction version: 02</p>


 
		<input disabled name="statePerson" type="text" class="input dop-box" placeholder="Select State..." />
		<input disabled name="revDate2" type="text" class="input dop-box" placeholder="Select Revision Date..." />


		
		
		<p style="font-weight:bold;color:white;text-align:left;margin: 20px 58px;font-size:36px;font-family: 'Poppins';">Name<label for="css-modal-checkbox" class="css-modal-checkbox"><img class='tooltip' name='nameTool' src='img/tooltips.png'/></label></p>
		<input name="name" type="text" class="input dop-box" placeholder="Name" />
		<input name="surname" type="text" class="input dop-box" placeholder="Surname" />
		<input name="middleName" type="text" class="input dop-box" placeholder="Middle Name (opt.)" />
		
		
		<p style="font-weight:bold;color:white;text-align:left;margin: 20px 58px;font-size:36px;font-family: 'Poppins';">Address<label for="css-modal-checkbox" class="css-modal-checkbox"><img class='tooltip' name='AddressTool' src='img/tooltips.png'/></label></p>
		<input name="streetAddress" type="text" class="input dop-box" placeholder="Street Address" />
		<input name="city" type="text" class="input dop-box" placeholder="City" />

		<div style="width: 100%;height:100px;margin: 15px auto;position: relative;">
			<input disabled style="width:40%;float:left;margin: 0 35px 0 60px;" name="stateAddress" type="text" class="input dop-box" placeholder="State" />
			<input style="width:40%;float:left;margin:0 auto" name="ZIP" type="text" class="input dop-box" placeholder="ZIP" />
		</div>
		
		<div style="width:100%;height:100%;"><button  class="btnInt" >Confirm the address</button></div>
		
		<p style="font-weight:bold;color:white;text-align:left;margin: 20px 58px;font-size:36px;font-family: 'Poppins';">Physical Description<label for="css-modal-checkbox" class="css-modal-checkbox"><img class='tooltip' name='PhysicalDescriptionTool' src='img/tooltips.png'/></label></p>
		
		<div style="width: 84%;height:150px;margin: 15px auto;position: relative;">
		
			<div style="width:47%;float:left;" class="select-box dop-box">
				<label for="select-box8" class="label select-box8"><span class="label-desc">Sex</span></label>
				<select name="sex" id="select-box8" class="select">
					<option value="-"> - </option>
					<option value="1">Male</option>
					<option value="2">Female</option>
				</select>
			</div>
			
			<div style="width:48%;float:right;" class="select-box dop-box">
				<label for="select-box9" class="label select-box9"><span class="label-desc">Race</span> </label>
				<select name="race" id="select-box9" class="select">
					<option value="-"> - </option>
					<option value="BALD">Bald</option>
					<option value="RED">Red/Auburn</option>
					<option value="BLACK">Black</option>
					<option value="SANDY">Sandy</option>
					<option value="BLOND">Blond</option>
					<option value="WHITE">White</option>
					<option value="BROWN">Brown</option>
					<option value="GRAY">Gray</option>
					<option value="UNKNOWN">Unknown</option>
				</select>
			</div>
		</div>
		
		<div style="width: 100%;height:100px;margin: 15px auto;position: relative;">
			<input style="width:40%;float:left;margin: 0 35px 0 60px;" name="height" type="text" class="input dop-box" placeholder="Height" />
			<input style="width:40%;float:left;margin:0 auto" name="weight" type="text" class="input dop-box" placeholder="Weight" />
		</div>
		

		<p style="font-weight:bold;color:white;text-align:left;margin: 20px 58px;font-size:36px;font-family: 'Poppins';">Special Marks<label for="css-modal-checkbox" class="css-modal-checkbox"><img class='tooltip' name='SpecialMarks' src='img/tooltips.png'/></label></p>

		<label class="checkbox-ios">
			<input type="checkbox">
			<span class="checkbox-ios-switch"></span>
			<p style="display:inline-block; color:#888888;font-size:24px;margin: 0 10px">The REAL ID mark</br><img src="img/star.png"/><span style="color:white;font-size:20px;"> Learn more</span></p>
		</label>

		<label class="checkbox-ios">
			<input type="checkbox">
			<span class="checkbox-ios-switch"></span>
			<p style="display:inline-block; color:#888888;font-size:24px;margin: 10px">Donor</p>
		</label>

		<label class="checkbox-ios">
			<input type="checkbox">
			<span class="checkbox-ios-switch"></span>
			<p style="display:inline-block; color:#888888;font-size:24px;margin: 10px">Veteran</p>
		</label>


		<p style="font-weight:bold;color:white;text-align:left;margin: 20px 58px;font-size:36px;font-family: 'Poppins';">Dates<label for="css-modal-checkbox" class="css-modal-checkbox"><img class='tooltip' name='Dates' src='img/tooltips.png'/></label></p>

		<input style="filter: invert(1);" name="datebirth" type="date" class="input dop-box" placeholder="Date of Birth" />

		<div style="width:100%;height:100%;"><button  class="btnInt" >Generate</button></div>
		<p style="color:#1BBB9B;font-size:24px;text-align: left;margin: 0px 60px 60px;">Your barcode is ready for downloading</p>



		<div style="width: 100%;height:100px;margin: 30px auto;position: relative;">
			<button style="color: white;width: 40%;float: left;margin: 0 35px 0 60px;background: #1BBB9B;padding: 20px;border-radius: 12px;font-size: 30px;" name="saveBarcode"  class="dop-box"  >Save Barcodes</button>
			<button style="width:40%;float:left;margin:0 auto;background: #d9d9d9;padding: 20px;border-radius: 12px;font-size: 30px;color:black;" name="history"  class="dop-box" >Check History</button>
		</div>



		 <div style="width:100%;height:100%;"><button  class="btnInt" >Generate</button></div>


		 Модальное окно 
			What's the Deal About Names?
			What's the Deal About Address?
			What's the Deal About Physical Description?
			What's the Deal About Special Marks?
			What's the Deal About Driving Classifications?
			What's the Deal About DL?
			What's the Deal About Dates on the DL?
			What's the Deal About Document Numbers?	
		    
		<input type="checkbox" id="css-modal-checkbox" />    
		<div class="cmc">
			<div class="cmt">
				<label for="css-modal-checkbox" class="css-modal-close"></label>
				<p id="modal-text">What's the Deal About Names?</p>
				<video id="videos" src="video/name.mp4" controls>
				</video>
				<button  class="btn2" >Got it! Continue!</button>
			</div>
			
		</div>
-->