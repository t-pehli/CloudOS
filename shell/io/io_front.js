/**
* 	 IO front-end logic
*/

// ----------- Constants & Globals ---------------
var waitTime = 1000;
// var errorTime = 5000;


var timeout;
var lastPath = "/";
// -----------------------------------------------


// --------------- State Machine -----------------
var STM = {
	state: "idle", // possible states: idle|running|blocked
	initialise: function () {
		$("#cmd").val("");

		$("#path").text("");
		this.state = "running";
		longpoll( "ping -s" );
	},
	stateEvent: function ( eventType, eventContent ) {

		switch( this.state ){
			// ===================================
			case "idle":
				if( eventType == "keypress" && eventContent == "enter" ){

					var mainDiv = $("#main");
					mainDiv.append( "<p>"+$("#path").text()+$("#cmd").val()+"</p>" );
					mainDiv.scrollTop( mainDiv.prop("scrollHeight") );
					$("#cmd").focus();

					if( $("#cmd").val() != "" ){
						
						$("#path").text("");
						this.state = "running";
						longpoll( $("#cmd").val() );
						$("#cmd").val("");
					}
				}
				break;
			// ===================================
			case "running":
				if( eventType == "poll" ){
					
					var i=0;
					while( i<eventContent.length && eventContent[i].type == "msg" ){
						// run though incoming printx msgs 

						var mainDiv = $("#main");
						mainDiv.append( "<p>"+eventContent[i].content+"</p>" );
						mainDiv.scrollTop( mainDiv.prop("scrollHeight") );
						$("#cmd").focus();
						i++;
					}

					if( i<eventContent.length && eventContent[i].type == "path" ){
						// check if a returnx path is left, finish

						lastPath = eventContent[i].content;
						$("#path").text( eventContent[i].content +">" );
						clearTimeout(timeout);

						this.state = "idle";
					}
					else if( i<eventContent.length && eventContent[i].type == "ping" ){
						// got a ping back, (re)start session

						// var mainDiv = $("#main");
						// mainDiv.append( "<p>Connected to CloudOS</p>" );

						$("#path").text( lastPath +">" );
						var mainDiv = $("#main");
						mainDiv.append( "<p>"+eventContent[i].content+"</p>" );
						mainDiv.scrollTop( mainDiv.prop("scrollHeight") );
						clearTimeout(timeout);

						this.state = "idle";
					}
					else if( i<eventContent.length && eventContent[i].type == "ask" ){
						// check if a scanx ask is left, block

						$("#path").text( eventContent[i].content +">" );
						clearTimeout(timeout);

						this.state = "blocked";
					}
					else {
						// if nothing is left, continue polling

					}
				}
				else if( eventType == "interrupt" ){
					
					$("#path").text( lastPath +">" );
					this.state = "idle";
					clearTimeout( timeout );
					sendInterrupt( eventContent );
				}
				break;
			// ===================================
			case "blocked":
				if( eventType == "keypress" && eventContent == "enter" ){
					
					$("#path").text("");
					this.state = "running";
					longpoll( $("#cmd").val() );
					$("#cmd").val("");
				}
				else if( eventType == "interrupt" ){

					
					$("#path").text( lastPath +">" );
					this.state = "idle";
					clearTimeout( timeout );
					sendInterrupt( eventContent );
				}
				break;
			// ===================================
		}
	}
}; 
// -----------------------------------------------

// ------------- Helper Functions ----------------
function sendInterrupt( msg ){

	var mainDiv = $("#main");
	mainDiv.append( "<p>Interrupted: "+msg+"</p>" );
	mainDiv.scrollTop( mainDiv.prop("scrollHeight") );
	$("#cmd").focus();
	
	jQuery.ajax({
		url: "/shell/io/buffer_manager.php",
		type: 'POST',
		dataType: 'JSON',
		data: { data: JSON.stringify( "interrupt "+msg ) }
	});
}

function longpoll( postData ){
	// perform longpoll

	var onSuccess = function(response){

		clearTimeout(timeout);
		timeout = setTimeout( function(){ longpoll(); }, waitTime);

		STM.stateEvent( "poll", response );		
	}

	var onFail = function(e){

		STM.stateEvent( "interrupt", e.responseText );
	}


	if (typeof postData == 'undefined'){
		
		jQuery.ajax({
			url: "/shell/io/buffer_manager.php",
			type: 'POST',
			dataType: 'JSON',
			data: { data: postData },
			success: onSuccess,
			error: onFail
		});
	}
	else {
		
		jQuery.ajax({
			url: "/shell/io/buffer_manager.php",
			type: 'POST',
			dataType: 'JSON',
			data: { data: JSON.stringify( postData ) },
			success: onSuccess,
			error: onFail
		});
	}

}
// -----------------------------------------------


// ------------------ Events ---------------------
$(document).keypress(function(e) {
	// Enter pressed, push command to buffer

	if(e.which == 13) {

		STM.stateEvent( "keypress", "enter" );
	}
	else if(e.which == 0) {

		STM.stateEvent( "interrupt", "Interrupted by user." );
	}
});

$("#wrapper").click( function(){

	$("#cmd").focus();
});
// -----------------------------------------------



// ---------------- Main Logic -------------------
$(function(){

	// Page Loaded

	STM.initialise();
});	
// -----------------------------------------------