(()=>{
    var base = 'https://api.kisss.ai'
     var token = localStorage.getItem('live_access_token')
    var userJson = localStorage.getItem('live_user_info')
	
    var user = JSON.parse(userJson)
   
   
   
   setTimeout(() => {
		if(token) {
		  
			
			  $('#useravatar').attr("src",user.avatar);
		
		
        $('#nickName').html(user.username);
		
		$('#Current_Plan').html(user.vip)
		
		if(user.expirytime!==""){
			$('#vip_expired_text').html("VIP expiration date:")
			$('#vip_expired_time').html(user.expirytime)
		}
		
		
		
		  $('#invite_link').html(user.score);
        $('#user_nickname').val(user.username)
        $('#invite_link').html("/?code="+user.salt)
		
		
		$('#user_tx_withdrawal').val(user.withdrawal)
		$('#user_tx_network').val(user.network)
		
		$('#withdrawal').html(user.withdrawal);
		$('#user_withdrawal').val(user.withdrawal)
		
		$('#network').html(user.network);
		$('#user_network').val(user.network)

        $('#gender').html(user.gender === 0 ? 'female' : 'male');
        $('#user_gender').val(user.gender === 0 ? 'female' : 'male')
        $('#E-mail').html(user.email);
        $('#change_user_email').val(user.email);
        $('#phoneNumber').html(user.mobile);
		$('#Money').html(user.money);
		$('#invite_num').html(user.invite_num);
		
        $('#user_phone_number').val(user.mobile)

        $('#saveNickname').on('click', function() {
            saveNickName()
        })
		$('#saveWithdrawal').on('click', function() {
		    saveWithdrawal()
		})
		
		$('#WithdrawalAmount').on('click', function() {
		    WithdrawalAmount()
		})
        $('#saveEmail').on('click', function() {
            saveEmail()
        })
        $('#savePwd').on('click', function() {
            savePwd()
        })
        $('#saveMobile').on('click', function() {
            saveMobile()
        })
        
          $('#copyhtmlbutton').on('click', function() {
            copy()
        })
        	getInviter()
		getMoneylog()
		getUserInfo()
		getWithdrawal()
		}else{
			window.location.href = "./Girls.html";

		}
		
	
	
		
    }, 100)
	 function getInviter(){
		 var headers = {}
		 if(token) {
		   headers = {
		     token
		   }
		 }
		 const form = new FormData();
		 form.append("page", 1);
		
		 const settings = {
		   "async": true,
		   "crossDomain": true,
		   "url":  base + "/api/user/inviterlist",
		   "method": "post",
		   "headers": headers,
		   "processData": false,
		   "contentType": false,
		   "mimeType": "multipart/form-data",
		   "data": form
		 };
		 $.ajax(settings).done(function (response) {
		   if(typeof(response) === 'string') {
		     response = JSON.parse(response)
		   }
		   var res = response
		    if(res.code === 1) {
		     localStorage.setItem('inviterList', JSON.stringify(res.data.pagelist.data))
		     inviterList(res.data.pagelist.data)
		   } else {
		     layer.msg(res.msg)
		   }
		 }).catch(error => console.error(error))
	 }
	 
	 function getWithdrawal(){
	     var headers = {}
	 		 if(token) {
	 		   headers = {
	 		     token
	 		   }
	 		 }
	 		 const form = new FormData();
	 		
	 		
	 		 const settings = {
	 		   "async": true,
	 		   "crossDomain": true,
	 		   "url":  base + "/api/user/withdrawal_fee",
	 		   "method": "post",
	 		   "headers": headers,
	 		   "processData": false,
	 		   "contentType": false,
	 		   "mimeType": "multipart/form-data",
	 		   "data": form
	 		 };
	 		 $.ajax(settings).done(function (response) {
	 		   if(typeof(response) === 'string') {
	 		     response = JSON.parse(response)
	 		   }
	 		   var res = response
	 		    if(res.code === 1) {
	 		        $('#withdrawal_fee').html(res.data.withdrawal_fee);
	 		         $('#withdrawal_amount').html(res.data.withdrawal_amount);
	 		   }
	 		 }).catch(error => console.error(error))
	 }
	 function getMoneylog(){
	 		 var headers = {}
	 		 if(token) {
	 		   headers = {
	 		     token
	 		   }
	 		 }
	 		 const form = new FormData();
	 		 form.append("page", 1);
	 		
	 		 const settings = {
	 		   "async": true,
	 		   "crossDomain": true,
	 		   "url":  base + "/api/user/moneylog",
	 		   "method": "post",
	 		   "headers": headers,
	 		   "processData": false,
	 		   "contentType": false,
	 		   "mimeType": "multipart/form-data",
	 		   "data": form
	 		 };
	 		 $.ajax(settings).done(function (response) {
	 		   if(typeof(response) === 'string') {
	 		     response = JSON.parse(response)
	 		   }
	 		   var res = response
	 		    if(res.code === 1) {
	 		     localStorage.setItem('moneylog', JSON.stringify(res.data.pagelist.data))
	 		     moneyList(res.data.pagelist.data)
	 		   } else {
	 		     layer.msg(res.msg)
	 		   }
	 		 }).catch(error => console.error(error))
	 }
	 
	 function moneyList(data) {
	 	 // $('#profilesLayout').html(cacheTemplate)
	 	 
	 	   $.each(data, function(index, item) {
	 		  
	 	     var template = $('#money-template').html();
	 		 
	 		 var templateto = $('#money-to-template').html();
	 		 if(item.money>0){
	 		 	item.money="+"+ item.money
	 		 					item.color="color:#43ff43;"
	 		 }else{
	 		 					
	 		 					 item.color="color:#ff5050;"
	 		 }
	 	     if(!template) return
			 if(item.type==1){
				 
				 var rendered = template.replace("{{form_user_username}}", item.form_user_username)
				 
				    .replace("{{imgUrl}}", item.avatar)
				 	 		 .replace("{{createtime}}", item.createtime)
							 .replace("{{en_memo}}", item.en_memo)
							 .replace("{{money}}", item.money)
				 	 		 .replace("{{Lv}}", item.form_user_lv)
							  .replace("{{color}}", item.color)
							 
							
								
							
			 }else{
				 item.tx_color=""
				  item.tx_text=""
				 
				 if(item.tx_type==1){
					 if(item.tx_status==0){
						 item.tx_color="color:rgb(255 255 255/var(--tw-text-opacity));"
						 item.tx_text="Under review"
					 }
					 if(item.tx_status==1){
						 item.tx_color="color:#43ff43;"
						 item.tx_text="Approved"
					 }
					 if(item.tx_status==2){
						 
					 	item.tx_color="color:#ff5050;"
						item.tx_text="Failed the review"
					 }
				 }
				
				 var renderedto = templateto.replace("{{createtime}}", item.createtime)
				 	 		  .replace("{{money}}", item.money)
							  .replace("{{color}}", item.color)
							   .replace("{{en_memo}}", item.en_memo)
							   .replace("{{tx_color}}", item.tx_color)
							   .replace("{{tx_text}}", item.tx_text)
			 }
	 	   
	 
	 	     
	 	     $('#moneyLayout').append(rendered);
			  $('#moneyLayout').append(renderedto);
	 	   });
	  }
	 
	function inviterList(data) {
		 // $('#profilesLayout').html(cacheTemplate)
		 
		   $.each(data, function(index, item) {
			  
		     var template = $('#inviter-template').html();
			 
			
			 
		     if(!template) return
		     var rendered = template.replace("{{userName}}", item.username)
		     .replace("{{imgUrl}}", item.avatar)
			 .replace("{{joinTime}}", item.jointime)
			 .replace("{{Lv}}", item.lv)
	
		     
		     $('#inviterLayout').append(rendered);
		   });
	 }
    
      function copy(){
     
        
        
        const text = $('#invite_link').html();

          const textarea = document.createElement('textarea');
          textarea.value = text;
          document.body.appendChild(textarea);
          textarea.select();
        
          try {
            // 尝试执行复制操作
            const success = document.execCommand('copy');
            if (success) {
            
             layer.msg("Copy Success")
            } else {
            
              layer.msg("Copy Failure")
            }
          } catch (error) {
          
           layer.msg("Copy Failure")
          }
        
          document.body.removeChild(textarea);
        
          
      }

    function saveNickName(){
        var headers = {}
        if(token) {
          headers = {
            token
          }
        }
        const form = new FormData();
        form.append("username", $('#user_nickname').val());
        const settings = {
          "async": true,
          "crossDomain": true,
          "url":  base + "/api/user/profile",
          "method": "post",
          "headers": headers,
          "processData": false,
          "contentType": false,
          "mimeType": "multipart/form-data",
          "data": form
        };
        $.ajax(settings).done(function (response) {
          if(typeof(response) === 'string') {
            response = JSON.parse(response)
          }
          var res = response
           if(res.code === 1) {
                // layer.msg(res.msg)
            //alert('修改成功')
            //getUserInfo();
            //location.reload()
            saveGender()
          } else {
            //  layer.msg(res.msg)
          }
        }).catch(error => console.error(error))
    }
	
	function saveWithdrawal(){
	    var headers = {}
	    if(token) {
	      headers = {
	        token
	      }
	    }
	    const form = new FormData();
	    form.append("withdrawal", $('#user_withdrawal').val());
		form.append("network", $('#user_network').val());
	    const settings = {
	      "async": true,
	      "crossDomain": true,
	      "url":  base + "/api/user/withdrawal_account",
	      "method": "post",
	      "headers": headers,
	      "processData": false,
	      "contentType": false,
	      "mimeType": "multipart/form-data",
	      "data": form
	    };
	    $.ajax(settings).done(function (response) {
	      if(typeof(response) === 'string') {
	        response = JSON.parse(response)
	      }
	      var res = response
	       if(res.code === 1) {
	       layer.msg(res.msg)
	       
	       getUserInfo();
	       setTimeout(() => {
            location.reload()
            }, 800);
	      } else {
	         layer.msg(res.msg)
	      }
	    }).catch(error => console.error(error))
	}
	
	function WithdrawalAmount(){
	    var headers = {}
	    if(token) {
	      headers = {
	        token
	      }
	    }
	    const form = new FormData();
	    
	    	form.append("user_tx_withdrawal", $('#user_tx_withdrawal').val());
	    		form.append("user_tx_network", $('#user_tx_network').val());
	    
		form.append("withdrawal_amount", $('#user_tx_anmount').val());
	    const settings = {
	      "async": true,
	      "crossDomain": true,
	      "url":  base + "/api/user/withdrawal_amount",
	      "method": "post",
	      "headers": headers,
	      "processData": false,
	      "contentType": false,
	      "mimeType": "multipart/form-data",
	      "data": form
	    };
	    $.ajax(settings).done(function (response) {
	      if(typeof(response) === 'string') {
	        response = JSON.parse(response)
	      }
	      var res = response
	       if(res.code === 1) {
	       layer.msg(res.msg);  
	       getUserInfo();
	       setTimeout(() => {
            location.reload()
            }, 800);
	      } else {
	       layer.msg(res.msg);  
	      }
	    }).catch(error => console.error(error))
	}

    function saveGender(){
        var headers = {}
        if(token) {
          headers = {
            token
          }
        }
        const form = new FormData();
        form.append("gender", $('#user_gender').val() === 'female' ? 1: 2);
        const settings = {
          "async": true,
          "crossDomain": true,
          "url":  base + "/api/user/gender",
          "method": "post",
          "headers": headers,
          "processData": false,
          "contentType": false,
          "mimeType": "multipart/form-data",
          "data": form
        };
        $.ajax(settings).done(function (response) {
          if(typeof(response) === 'string') {
            response = JSON.parse(response)
          }
          var res = response
           if(res.code === 1) {
            layer.msg(res.msg);  
            getUserInfo();
            setTimeout(() => {
            location.reload()
            }, 800);
          } else {
            layer.msg(res.msg);  
          }
        }).catch(error => console.error(error))
    }
    function saveEmail(){
        var headers = {}
        if(token) {
          headers = {
            token
          }
        }
        const form = new FormData();
        form.append("email", $('#change_user_email').val());
        const settings = {
          "async": true,
          "crossDomain": true,
          "url":  base + "/api/user/changeemail",
          "method": "post",
          "headers": headers,
          "processData": false,
          "contentType": false,
          "mimeType": "multipart/form-data",
          "data": form
        };
        $.ajax(settings).done(function (response) {
          if(typeof(response) === 'string') {
            response = JSON.parse(response)
          }
          var res = response
           if(res.code === 1) {
            layer.msg(res.msg); 
            getUserInfo();
            setTimeout(() => {
            location.reload()
            }, 800);
          } else {
            layer.msg(res.msg); 
          }
        }).catch(error => console.error(error))
    }
    function saveMobile(){
        var headers = {}
        if(token) {
          headers = {
            token
          }
        }
        const form = new FormData();
        form.append("mobile", $('#user_phone_number').val());
        const settings = {
          "async": true,
          "crossDomain": true,
          "url":  base + "/api/user/changemobile",
          "method": "post",
          "headers": headers,
          "processData": false,
          "contentType": false,
          "mimeType": "multipart/form-data",
          "data": form
        };
        $.ajax(settings).done(function (response) {
          if(typeof(response) === 'string') {
            response = JSON.parse(response)
          }
          var res = response
           if(res.code === 1) {
           layer.msg(res.msg)
            getUserInfo();
          } else {
            layer.msg(res.msg)
          }
        }).catch(error => console.error(error))
    }
    function savePwd(){
        var headers = {}
        if(token) {
          headers = {
            token
          }
        }
        const form = new FormData();
        form.append("newpassword", $('#user_new_password').val());
        const settings = {
          "async": true,
          "crossDomain": true,
          "url":  base + "/api/user/resetpwd",
          "method": "post",
          "headers": headers,
          "processData": false,
          "contentType": false,
          "mimeType": "multipart/form-data",
          "data": form
        };
        $.ajax(settings).done(function (response) {
          if(typeof(response) === 'string') {
            response = JSON.parse(response)
          }
          var res = response
           if(res.code === 1) {
             layer.msg(res.msg); 
            getUserInfo();
            setTimeout(() => {
            location.reload()
            }, 800);
          } else {
             layer.msg(res.msg); 
          }
        }).catch(error => console.error(error))
    }
    function getUserInfo(){
       var headers = {}
       if(token) {
         headers = {
           token
         }
       }
       const settings = {
         "async": true,
         "crossDomain": true,
         "url":  base + "/api/user/user_info",
         "method": "POST",
         "headers": headers,
         "processData": false,
         "contentType": false,
         "mimeType": "multipart/form-data",
       };
       
       $.ajax(settings).done(function (response) {
         if(typeof(response) === 'string') {
           response = JSON.parse(response)
         }
         var res = response
          if(res.code === 1) {
           localStorage.setItem('userInfo', JSON.stringify(res.data))
           
           // location.reload()
         } else {
          layer.msg(res.msg);  
         }
       }).catch(error => console.error(error))
      }
})()