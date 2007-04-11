<!--
var today = new Date();
var expires = new Date(today.getTime() + (56 * 86400000));

function closeAndRefresh()
{
    opener.location.href = opener.location;
    window.close();
}

function closeAndGotoList()
{
    opener.location.href = 'list.php';
    window.close();
}


function unhideRow(element_name, table_name)
{
	var tbl = document.getElementById(table_name);
    var rows = tbl.rows.length;
	var maxEmptyRow = 1;
	var emptyRowExists = 0;
	for(x=1;x<rows;x++) {
		var row = document.getElementById('tr_' + element_name + '_' + x);
		var rowInput = document.getElementById(element_name + '_' + x);

		if (row.style.display == '' && rowInput.value == '') {
			emptyRowExists = 1;
		}
		if (row.style.display != '') {
			maxEmptyRow = x;
			break;
		}
	}
	if (maxEmptyRow > 1 && emptyRowExists == 0) {
		var show_tr = document.getElementById('tr_' + element_name + '_' + x);
		show_tr.style.display = '';
	}
}

function str_replace(s, srch, rplc) {
  var tmp = s;
  var tmp_before = new String();
  var tmp_after = new String();
  var tmp_output = new String();
  var int_before = 0;
  var int_after = 0;

  while (tmp.toUpperCase().indexOf(srch.toUpperCase()) > -1) {   
    int_before = tmp.toUpperCase().indexOf(srch.toUpperCase());
    tmp_before = tmp.substring(0, int_before);
    tmp_output = tmp_output + tmp_before;
    tmp_output = tmp_output + rplc;
    int_after = tmp.length - srch.length + 1;
    tmp = tmp.substring(int_before + srch.length);
  }

  return tmp_output + tmp;
}


function displayFixedWidth(element_name)
{
    var el = getPageElement(element_name);
    // only do this for mozilla
    if (is_nav6up) {
        var content = el.innerHTML;
        el.innerHTML = '<pre>' + str_replace(content, "<br>", '') + '</pre>';
        el.className = '';
    }
    el.style.fontFamily = 'Mono, Monaco, Courier New, Courier';
    el.style.whiteSpace = 'pre';
}

function showSelections(form_name, field_name)
{
    var f = getForm(form_name);
    var field = getFormElement(f, field_name);
    var selections = getSelectedItems(field);
    var selected_names = new Array();
    for (var i = 0; i < selections.length; i++) {
        selected_names.push(selections[i].text);
    }
    var display_div = getPageElement('selection_' + field_name);
    display_div.innerHTML = 'Current Selections: ' + selected_names.join(', ');
}


// @@@ CK - 3/9/2004 - Added so the fill user name will fill the text box
function showSelectionsFill(form_name, field_name, fld_id)
{
    var f = getForm(form_name);
    var field = getFormElement(f, field_name);
    var selections = getSelectedItems(field);
    var selected_names = new Array();
    for (var i = 0; i < selections.length; i++) {
        selected_names.push(selections[i].text);
    }
    var display_div = getPageElement('custom'+fld_id);
//	if (isWhitespace(display_div.value)) { 
      display_div.value = selected_names.join(', ');
/*	} else {
		selected_names.push(display_div.value);
		display_div.value = selected_names.join(', ');
	} */
	
}

function replaceWords(str, original, replacement)
{
    var lines = str.split("\n");
    for (var i = 0; i < lines.length; i++) {
        lines[i] = replaceWordsOnLine(lines[i], original, replacement);
    }
    return lines.join("\n");
}

function replaceWordsOnLine(str, original, replacement)
{
    var words = str.split(' ');
    for (var i = 0; i < words.length; i++) {
        words[i] = words[i].replace(/^\s*/, '').replace(/\s*$/, ''); 
        if (words[i] == original) {
            words[i] = replacement;
        }
    }
    return words.join(' ');
}

function checkSpelling(form_name, field_name)
{
    var features = 'width=420,height=400,top=30,left=30,resizable=no,scrollbars=yes,toolbar=no,location=no,menubar=no,status=no';
    var popupWin = window.open('spell_check.php?form_name=' + form_name + '&field_name=' + field_name, '_spellChecking', features);
    popupWin.focus();
}

function updateTimeFields(form_name, year_field, month_field, day_field, hour_field, minute_field)
{
    var f = getForm(form_name);
    var current_date = new Date();
    selectOption(f, month_field, padDateValue(current_date.getMonth()+1));
    selectOption(f, day_field, padDateValue(current_date.getDate()));
    selectOption(f, year_field, current_date.getFullYear());
    selectOption(f, hour_field, padDateValue(current_date.getHours()));
    selectOption(f, minute_field, padDateValue(current_date.getMinutes()));
}

function padDateValue(str)
{
    if (str.length == 1) {
        str = '0' + str;
    }
    return str;
}

function resizeTextarea(page_name, form_name, field_name, change)
{
    var f = getForm(form_name);
    var field = getFormElement(f, field_name);
    field.cols = field.cols + change;
    var cookie_name = 'textarea_' + page_name + '_' + field_name;
    setCookie(cookie_name, field.cols, expires);
}

function removeOptionByValue(f, field_name, value)
{
    var field = getFormElement(f, field_name);
    for (var i = 0; i < field.options.length; i++) {
        if (field.options[i].value == value) {
            field.options[i] = null;
        }
    }
}

function getTotalCheckboxes(f, field_name)
{
    var total = 0;
    for (var i = 0; i < f.elements.length; i++) {
        if (f.elements[i].name == field_name) {
            total++;
        }
    }
    return total;
}

function getTotalCheckboxesChecked(f, field_name)
{
    var total = 0;
    for (var i = 0; i < f.elements.length; i++) {
        if ((f.elements[i].name == field_name) && (f.elements[i].checked)) {
            total++;
        }
    }
    return total;
}

// @@@ CK - 20/10/2004 - Added for the forward email functionality
// Will get the bottom checked field, but will only be used for when there is only one checked.
function getCheckboxChecked(f, field_name)
{
    var returnNum = false;
    for (var i = 0; i < f.elements.length; i++) {
        if ((f.elements[i].name == field_name) && (f.elements[i].checked)) {
           returnNum = f.elements[i].value;
        }
    }
    return returnNum;
}

function hideComboBoxes(except_field)
{
    for (var i = 0; i < document.forms.length; i++) {
        for (var y = 0; y < document.forms[i].elements.length; y++) {
            if (((document.forms[i].elements[y].type == 'select-one') ||
                 (document.forms[i].elements[y].type == 'select-multiple')) && 
                    (document.forms[i].elements[y].name != except_field) &&
                    (document.forms[i].elements[y].name != 'lookup') &&
                    (document.forms[i].elements[y].name != 'lookup[]')) {
                document.forms[i].elements[y].style.visibility = 'hidden';
            }
        }
    }
}

function showComboBoxes()
{
    for (var i = 0; i < document.forms.length; i++) {
        for (var y = 0; y < document.forms[i].elements.length; y++) {
            if (((document.forms[i].elements[y].type == 'select-one') ||
                 (document.forms[i].elements[y].type == 'select-multiple')) && 
                    (document.forms[i].elements[y].name != 'lookup') &&
                    (document.forms[i].elements[y].name != 'lookup[]')) {
                document.forms[i].elements[y].style.visibility = 'visible';
            }
        }
    }
}

function getOverlibContents(options, target_form, target_field, is_multiple)
{
    hideComboBoxes(target_field);
    var html = '<form onSubmit="javascript:return lookupOption(this, \'' + target_form + '\', \'' + target_field + '\');">' + options + '<input class="button_overlib" type="submit" value="Lookup"><br><input name="search" class="lookup_field_overlib" type="text" size="24" value="paste or start typing here" onBlur="javascript:this.value=\'paste or start typing here\';" onFocus="javascript:this.value=\'\';" onKeyUp="javascript:lookupField(this.form, this, \'lookup';
    if ((is_multiple != null) && (is_multiple == true)) {
        html += '[]';
    }
    html += '\');"></form>';
    return html;
}

function getFillInput(options, target_form, target_field)
{
    hideComboBoxes(target_field);
    return '<form onSubmit="javascript:return fillInput(this, \'' + target_form + '\', \'' + target_field + '\');">' + options + '<input class="button_overlib" type="submit" value="Lookup"><br><input name="search" class="lookup_field_overlib" type="text" size="24" value="paste or start typing here" onBlur="javascript:this.value=\'paste or start typing here\';" onFocus="javascript:this.value=\'\';" onKeyUp="javascript:lookupField(this.form, this, \'lookup\');"></form>';
}

function lookupOption(f, target_form, target_field)
{
    var w = document;
    for (var i = 0; i < w.forms.length; i++) {
        if (w.forms[i].name == target_form) {
            var test = getFormElement(f, 'lookup');
            if (!test) {
                var field = getFormElement(f, 'lookup[]');
                var target = getFormElement(getForm(target_form), target_field);
                clearSelectedOptions(target);
                selectOptions(w.forms[i], target_field, getSelectedItems(field));
            } else {
                selectOption(w.forms[i], target_field, getSelectedOption(f, 'lookup'));
            }
            nd();
            showComboBoxes();
            break;
        }
    }
    return false;
}

function fillInput(f, target_form, target_field)
{
    var exists = getFormElement(f, 'lookup');
    var target_f = getForm(target_form);
    if (!exists) {
        var field = getFormElement(f, 'lookup[]');
        var target_field = getFormElement(target_f, target_field);
        target_field.value = '';
        var values = getValues(getSelectedItems(field));
        target_field.value = values.join('; ');
        errorDetails(target_f, target_field, false);
    } else {
        var field_value = getSelectedOption(f, 'lookup');
        var field = getFormElement(target_f, target_field);
        field.value = field_value;
        errorDetails(target_f, target_field, false);
    }
    nd();
    showComboBoxes();
    return false;
}

function lookupField(f, search_field, field_name, callbacks)
{
    var search = search_field.value;
    if (isWhitespace(search)) {
        return false;
    }
    var target_field = getFormElement(f, field_name);
    for (var i = 0; i < target_field.options.length; i++) {
        var value = target_field.options[i].text.toUpperCase();
        if (startsWith(value, search.toUpperCase())) {
            // if we are targetting a multiple select box, then unselect everything
            // before selecting the matched option
            if (target_field.type == 'select-multiple') {
                clearSelectedOptions(target_field);
            }
            target_field.options[i].selected = true;
            // handle calling any callbacks
            if (callbacks != null) {
                for (var y = 0; y < callbacks.length; y++) {
                    eval(callbacks[y] + ';');
                }
            }
            return true;
        }
    }
    target_field.selectedIndex = 0;
}

function clearSelectedOptions(field)
{
    for (var i = 0; i < field.options.length; i++) {
        field.options[i].selected = false;
    }
}

function selectAllOptions(f, field_name)
{
	var field = getFormElement(f, field_name);
    for (var y = 0; y < field.options.length; y++) {
        field.options[y].selected = true;
    }
}

function selectOptions(f, field_name, values)
{
    var field = getFormElement(f, field_name);
    for (var i = 0; i < values.length; i++) {
        for (var y = 0; y < field.options.length; y++) {
            if (field.options[y].value == values[i].value) {
                field.options[y].selected = true;
            }
        }
    }
}

function selectCustomOptions(f, field_name, values)
{
    var field = getFormElement(f, field_name);
    for (var i = 0; i < values.length; i++) {
        for (var y = 0; y < field.options.length; y++) {
            if (field.options[y].value == values[i]) {
                field.options[y].selected = true;
            }
        }
    }
}

function small_window(myurl) {
	var newWindow;
	var props = 'scrollBars=yes,resizable=yes,toolbar=no,menubar=no,location=no,directories=no,top=50,left=100,width=800,height=550';
	newWindow = window.open(myurl, "Add_from_Src_to_Dest", props);
}

// Adds the list of selected items selected in the child
// window to its list. It is called by child window to do so.
function addToParentList(sourceList, destinationList) {
//	destinationList = window.document.report_form.parentList;
//	destinationList = parentList;

	for(var count = destinationList.options.length - 1; count >= 0; count--) {
		for(var i = 0; i < sourceList.options.length; i++) {
			if (destinationList.options[count]) {
				if ((sourceList.options[i] != null) && (sourceList.options[i].selected) && (destinationList.options[count].value == sourceList.options[i].value)) {
					destinationList.options[count] = null;
				}
			}
		}
	} 
	var len = destinationList.length;
	for(var i = 0; i < sourceList.options.length; i++) {
		if ((sourceList.options[i] != null) && (sourceList.options[i].selected)) {
		   destinationList.options[len] = new Option(sourceList.options[i].text, sourceList.options[i].value );
		   destinationList.options[len].selected = true;
		   len++;
		}
   	}
}
// Deletes the selected items of supplied list.
function deleteSelectedItemsFromList(sourceList) {
	var maxCnt = sourceList.options.length;
	for(var i = maxCnt - 1; i >= 0; i--) {
		if ((sourceList.options[i] != null) && (sourceList.options[i].selected == true)) {
			sourceList.options[i] = null;
      		}
   	}
}

function selectOption(f, field_name, value)
{
    for (var i = 0; i < f.elements.length; i++) {
        if (f.elements[i].name == field_name) {
			field = f.elements[i];
            for (var i = 0; i < field.options.length; i++) {
                if (field.options[i].value == value) {
                    field.options[i].selected = true;
                    return true;
                }
            }
			return false;
        }
    }
	return false;
}

function setHiddenFieldValue(f, field_name, value)
{
    var field = getFormElement(f, field_name);
    field.value = value;
}

function getForm(form_name)
{
    for (var i = 0; i < document.forms.length; i++) {
        if (document.forms[i].name == form_name) {
            return document.forms[i];
        }
    }
}

function getPageElement(name)
{
    if (document.getElementById) {
        return document.getElementById(name);
    } else if (document.all) {
        return document.all[name];
    }
}

function getOpenerPageElement(name)
{
    if (window.opener.document.getElementById) {
        return window.opener.document.getElementById(name);
    } else if (window.opener.document.all) {
        return window.opener.document.all[name];
    }
}

function getFormElement(f, field_name, num)
{
//	alert(field_name);
    var y = 0;
    for (var i = 0; i < f.elements.length; i++) {
        if (num != null) {
            if (f.elements[i].name == field_name) {
                if (y == num) {
                    return f.elements[i];
                }
                y++;
            }
        } else {

			if (f.elements[i].name == field_name) {
//				alert('found a match'); 
//				alert(f.elements[i].name); 
				return f.elements[i];
            }
        }
    }
    return false;
}

function getSelectedItems(field)
{
    var selected = new Array();
    for (var i = 0; i < field.options.length; i++) {
        if (field.options[i].selected) {
            selected[selected.length] = field.options[i];
        }
    }
    return selected;
}

function removeAllOptions(f, field_name)
{
    var field = getFormElement(f, field_name);
	if (field.options == null) { return false; }
	if (field.options.length > 0) {
        field.options[0] = null;
        removeAllOptions(f, field_name);
    }
}

function getValues(list)
{
    var values = new Array();
    for (var i = 0; i < list.length; i++) {
        values[values.length] = list[i].value;
    }
    return values;
}

function optionExists(field, option)
{
	if (field.options == null) { return false; }
	for (var i = 0; i < field.options.length; i++) {
        if (field.options[i].text == option.text) {
            return true;
        }
    }
    return false;
}

function addOptions(f, field_name, options)
{
    var field = getFormElement(f, field_name);
	if (field.options == null) { return false; }
	for (var i = 0; i < options.length; i++) {
        if (!optionExists(field, options[i])) {
            field.options.length = field.options.length + 1;
            field.options[field.options.length-1].text = options[i].text;
            field.options[field.options.length-1].value = options[i].value;
            field.options[field.options.length-1].selected = options[i].selected;
		}
    }
}

function replaceParam(str, param, new_value)
{
    if (str.indexOf("?") == -1) {
        return param + "=" + new_value;
    } else {
        var pieces = str.split("?");
        var params = pieces[1].split("&");
        var new_params = new Array();
        for (var i = 0; i < params.length; i++) {
            if (params[i].indexOf(param + "=") == 0) {
                params[i] = param + "=" + new_value;
            }
            new_params[i] = params[i];
        }
        // check if the parameter doesn't exist on the URL
        if ((str.indexOf("?" + param + "=") == -1) && (str.indexOf("&" + param + "=") == -1)) {
            new_params[new_params.length] = param + "=" + new_value;
        }
        return new_params.join("&");
    }
}

function checkRadio(form_name, field_name, num)
{
    var f = getForm(form_name);
    var field = getFormElement(f, field_name, num);
    field.checked = true;
}

function toggleCheckbox(form_name, field_name, num)
{
    var f = getForm(form_name);
    var checkbox = getFormElement(f, field_name, num);
    if (checkbox.disabled) {
        return false;
    }
    if (checkbox.checked) {
        checkbox.checked = false;
    } else {
        checkbox.checked = true;
    }
}

var toggle = 'off';
function toggleSelectAll(f, field_name)
{
    for (var i = 0; i < f.elements.length; i++) {
        if (f.elements[i].name == field_name) {
            if (toggle == 'off') {
                f.elements[i].checked = true;
            } else {
                f.elements[i].checked = false;
            }
        }
    }
    if (toggle == 'off') {
        toggle = 'on';
    } else {
        toggle = 'off';
    }
}

function getCookies()
{
    var t = new Array();
    var pieces = new Array();
    var cookies = new Object();
    if (document.cookie) {
        t = document.cookie.split(';');
        for (var i = 0; i < t.length; i++) {
            pieces = t[i].split('=');
            eval('cookies.' + pieces[0].replace('[', '_').replace(']', '_') + ' = "' + pieces[1] + '";');
        }
        return cookies;
    }
}

function isElementVisible(element)
{
    if ((!element.style.display) || (element.style.display == getDisplayStyle())) {
        return true;
    } else {
        return false;
    }
}

function toggleVisibility(title, keep_basic_filter_form, create_cookie)
{
    var element = getPageElement(title + '1');
    if (isElementVisible(element)) {
        var new_style = 'none';
        if (title != 'basic_filter_form' && keep_basic_filter_form != 1) { 
            var basic_element = getPageElement('basic_filter_form' + '1');
			if (isElementVisible(basic_element)) {
				toggleVisibility('basic_filter_form');
			}
		}
        
    } else {
        var new_style = getDisplayStyle();
        if (title != 'basic_filter_form' && keep_basic_filter_form != 1) { 
            var basic_element = getPageElement('basic_filter_form' + '1');
			if (!isElementVisible(basic_element)) {
				toggleVisibility('basic_filter_form');
			}
		}
    }
    var i = 1;
    while (1) {
        element = getPageElement(title + i);
        if (!element) {
            break;
        }
        element.style.display = new_style;
        i++;
    }
    // if any elements were found, then...
    if (i > 1) {
        var link_element = getPageElement(title + '_link');
        if (link_element) {
            if (new_style == 'none') {
                link_element.innerHTML = 'show';
                link_element.title = 'show details about this section';
            } else {
                link_element.innerHTML = 'hide';
                link_element.title = 'hide details about this section';
            }
        }
    }
    if (((create_cookie == null) || (create_cookie == false)) && (create_cookie != undefined)) {
        return false;
    } else {
        setCookie('visibility_' + title, new_style, expires);
    }
}

function getDisplayStyle()
{
    // kind of hackish, but it works perfectly with IE6 and Mozilla 1.1
    if (is_ie5up) {
        return 'block';
    } else if (is_nav6up) {
        return '';
    }
}

function getCookie(name)
{
    var start = document.cookie.indexOf(name+"=");
    var len = start+name.length+1;
    if ((!start) && (name != document.cookie.substring(0,name.length))) return null;
    if (start == -1) return null;
    var end = document.cookie.indexOf(";",len);
    if (end == -1) end = document.cookie.length;
    return unescape(document.cookie.substring(len,end));
}

function setCookie(name, value, expires, path, domain, secure)
{
    document.cookie = name + "=" +escape(value) +
        ( (expires) ? ";expires=" + expires.toGMTString() : "") +
        ( (path) ? ";path=" + path : "") + 
        ( (domain) ? ";domain=" + domain : "") +
        ( (secure) ? ";secure" : "");
}

function openHelp(rel_url, topic)
{
    var width = 550;
    var height = 500;
    var w_offset = 30;
    var h_offset = 30;
    var location = 'top=' + h_offset + ',left=' + w_offset + ',';
    if (screen.width) {
        location = 'top=' + h_offset + ',left=' + (screen.width - (width + w_offset)) + ',';
    }
    var features = 'width=' + width + ',height=' + height + ',' + location + 'resizable=no,scrollbars=yes,toolbar=no,location=no,menubar=no,status=no';
    var helpWin = window.open(rel_url + 'help.php?topic=' + topic, '_help', features);
    helpWin.focus();
}
//-->
