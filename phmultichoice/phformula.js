/* this file contains all the function used to handle with 'formula' type strings */

var formula; //this variable contains the fomula which is in editing
//TODO:it's maybe more correct, to convert formula to class object, for OOP design.


/**
  *insert the formula from the formula input , into the html editor 
  *te - the name of the target element(target html editor)
  *se - the source element - the hidden element which contains the html of the formula input
  *called by the Insert button on the form
  *it insert the formula html into the editor,wrapped inside table from Code class
*/


function insertMathExpression(htmleditor,tbmathexp) {
    HTMLArea._object=htmleditor;
	var editor=HTMLArea._object;
	editor.focusEditor();
    var spanopen='<span class="mathExpression" style="background-color: rgb(187, 210, 242); padding-left: 5px; padding-right: 5px;" dir="ltr">';
	var spanclose='</span>';
	editor.insertHTML(spanopen+tbmathexp.value+spanclose);
}


/*****************************************************************************************/
/**
  *find the html editor object, the object name is editor_MD5(textarea_name),
  *and set the HTMLArea._object global class variable to refference the object
*/
function setTarget(te)
{

	editor = eval("editor_"+MD5(te));
	HTMLArea._object=editor;
}
/*******************************************


/*******************************************************************************************/
/**
   *this isn't really a function, it's a constructor of paramobj class
   *example of use : var pObj=new paramobj(id,name,value,order)
   *it's used to store data about params that used in the question
   *id-  specifiec  param id
   *name - param name
   *value - param value
   *order - param order
   *all this data source if from the db, when question params were already stored in db
*/
function paramobj(id,name,value,order)
{
	this.id=id;
	this.name=name;
	this.value=value;
	this.order=order;
}
/**
 *called when in question editing page the button "save changes" is pressed
 *this function generates the question params table, so that they will be posted,
 *and stored, to be ready for next page (params page) to load them from the db
 *type - type of question , m for multichoise, n - for numerical
 *me - only for insertion place of the table, usually before the "save changes" button
*/
function createVariArr(type,me)
{
	var vObj=new Array();//will contain the array of currently in db params
			     //it's important when the question is in edit mode 
			     //and not newly created
	var vCount=0;//the number of params that were loaded from the db.
	/*to initialize the array above(vObj) and vCount, code was generated in php, it contained in hidden element called params, this code initializing the vObj array, to contain 
          data about the currently used params (which were loaded from db)
	  you can see how this code is create in file edit_ph_....._form.php
          func set_data
	*/
	eval(document.getElementById('params').value);//evaluate the code that is written 
							//in params element
	var formula="";//this variable will contain the questiontext, and question answers strings
		       //and the extravars , it will be searched for variables elements
	setTarget('questiontext');//find the questiontext html editor
	formula+=HTMLArea._object.getHTML();//get it's HTML
	i=0;
	while(element=document.getElementsByName('answer['+i+']')[0])
	//loop all the answer blocks
	//if multichoise get data from the html editors
	//if numerical get data from the hidden html boxes
	{
		if(type=='m')
		{
			setTarget(element.name);
			formula+=HTMLArea._object.getHTML();
		}
		else
		{
			formula+='##'+element.value+'##';
		}
		i++;
	}
	//formula+=document.getElementsByName('aextra')[0].value;//add the extravars string to formula
	varContainer=document.getElementById('varContainer');
	//the above is a div that contains the variables table
	
	if(varContainer)//if it excists, we will delete it, and build new one to apply
			//the changes that were maybe made(like new variables)
	{
		varContainer.parentNode.removeChild(varContainer);
	}
	//create new varContainer div
	varContainer=document.createElement('div');
	varContainer.setAttribute('id','varContainer');
	//insert it relativly to "me" element(doesn't really matter)
	me.parentNode.insertBefore(varContainer,me.nextSibling);
	
	//for handeling the formula string that was created, to search it for var elements
	//I used regular expressions, searching for speciefic pattern used for variables
	//the variable is between var open tag, and var close tag:<var>...</var>
	//and for the code not to find the same variable again, in the loop
	//exclude string is built, contains the variables the were found.
	str_parts=formula.split('##');
	reg_ker="([^{}]+)";
	reg_exclude_open="(?!";
	reg_exclude_close=")";
	var reg=new RegExp("{"+reg_ker+"}",'i');
	counter=0;
	var innerhtml="";
	for(i=1;i<str_parts.length;i+=2)
	{
		
		//alert(str_parts[i]);
		
		do
		{

			reg_res=reg.exec(str_parts[i]);
	
			if(reg_res)
			{
			
				reg_exclude_open+=reg_res[1]+"}";
				reg=new RegExp("{"+reg_exclude_open+reg_exclude_close+reg_ker+"}",'i');
				reg_exclude_open+="|";	
				varname='$'+reg_res[1];//variable name
				varvalue='';//variable value
				isrand="";//is range
				isvalue="Checked";//is constant value
				randfrom='""';//range - from
				randto='""';//range - to
				randstep='""';//range - step
				varorder='';//variable order
				id=-1;//variable default id
				if(vObj[varname])
				//if this variable is in db we need to initialize it with it's data
				{
					varvalue=vObj[varname].value;
					if(varvalue.indexOf('rand')>=0)//if the value contains rand
					//if yes, we need to seperate the parts of rand:
					{
						isrand="Checked";
						isvalue="";
						varvalue="\"\"";
						randfrom=vObj[varname].value.substr(8,vObj[varname].value.indexOf('/')-9);
						randto=randfrom.substr(0,randfrom.indexOf('-',1));
						randfrom='"'+randfrom.substr(randto.length+1,randfrom.length-1)+'"';
						randto='"'+randto+'"';
						randstep=vObj[varname].value.substr(vObj[varname].value.indexOf('*')+1,vObj[varname].value.length-vObj[varname].value.indexOf('*')+1);
						randstep='"'+randstep.split('+')[0]+'"';

					}
				varorder=vObj[varname].order;
				id=vObj[varname].id;
			}	
			//the html of the variable row, the user doesn't see it
			//it's only for posting the data to the db
			//the next page of the wizard will load the variables from the db
			innerhtml+='<tr><td valign="top"><input type="text" name="varorder'+counter+'" value="'+varorder+'"/><input type="hidden" name="vId'+counter+'" value="'+id+'"/><input type="hidden" name="v'+counter+'" value="'+varname+'"/>'+varname+'</td><td><input type="radio" name="vr'+counter+'" id="vr'+counter+'" value="0" '+isvalue+'/> Value: <input type="text" name="value'+counter+'" id="value'+counter+'" value="'+varvalue+'" size="40"/><br/><input type="radio" name="vr'+counter+'" id="vr'+counter+'" value="1"'+isrand+'/> Range: from - <input type="text" name="from'+counter+'" id="from'+counter+'" size="5" value='+randfrom+'/> to - <input type="text" name="to'+counter+'" id="to'+counter+'" size="5" value='+randto+'> step - <input type="text" name="step'+counter+'" id="step'+counter+'" size="5" value='+randstep+'/></td></tr>';
			counter++;
			}		
		}while(reg_res);
		
	}
	
	varContainer.innerHTML="<table style=\"display:;\">"+ innerhtml +"</table>";;
	//add the html to varContainer
}


