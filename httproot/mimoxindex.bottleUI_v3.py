#!/usr/bin/python -u
# -*- coding: utf-8 -*-


import os, time, socket, MySQLdb, re, hashlib, csv
from os import listdir
from os.path import isfile, join
import json
import smtplib
from recaptcha import RecaptchaClient
import uuid
import sys
import cPickle as pickle
reload(sys)
sys.setdefaultencoding('utf-8')

try:
	import bottle
	from bottle import route, run, template, error, get, post, request, abort, response, auth_basic, hook, static_file, redirect
	from beaker.middleware import SessionMiddleware
except Exception,e:
	print "Cannot import 3rd party lib, sysexit, ",str(e)
	sys.exit(1)

##########################################
# Init session store
session_opts = {
    'session.type': 'file',
    'session.cookie_expires': 300,
    'session.data_dir': '/mnt',
    'session.auto': True
}
app = SessionMiddleware(bottle.app(), session_opts)

##########################################
#Config
##########################################
RECAPTCHA_PRIVKEY=os.environ.get('RECAPTCHA_PRIVKEY')
RECAPTCHA_PUBKEY=os.environ.get('RECAPTCHA_PUBKEY')
GMAIL_PWD=os.environ.get('GMAIL_PWD')
GMAIL_USER=os.environ.get('GMAIL_USER')
GMAIL_SMTP=os.environ.get('GMAIL_SMTP')
RUN_IP=os.environ.get('RUN_IP')
RUN_PORT=int(os.environ.get('RUN_PORT'))
DBHOST=os.environ.get('DBHOST')
DBUSER=os.environ.get('DBUSER')
DBPASS=os.environ.get('DBPASS')
DBNAME=os.environ.get('DBNAME')
TEMPLATE=os.environ.get('TEMPLATE')
ADMIN_TEMPLATE=os.environ.get('ADMIN_TEMPLATE')
UPLOADS=os.environ.get('UPLOADS')
CACHE=os.environ.get('CACHE')
CACHE_TIME=3600 #1h
CACHE_USE=True

TERM_EMAIL_RECPS=['tamas.tobi@gmail.com','mimoxindex@mimox.com']
##########################################

def Mailer(subj,msg,sender,recp):
	try:
		message = """From: %s\nTo: %s\nSubject: %s\n\n%s\n""" % (sender, ", ".join(recp), subj, msg+"\n\n"+time.strftime('%H:%M:%S',time.localtime()))
		server = smtplib.SMTP(GMAIL_SMTP)
		server.ehlo()
		server.starttls()
		server.login(GMAIL_USER,GMAIL_PWD)
		server.sendmail(sender, recp, message)
		server.quit()
		print 'Mailer: Mail sent for: ',str(recp)
	except Exception, e:
		print 'Mailer: Mail sending exception. Message:',str(msg),str(recp),str(e)
		pass


@hook('before_request')
def setup_request():
	request.session = request.environ['beaker.session']

@route('/fulllist/<yesno>', method='GET')
def checkfulllist(yesno="no"):
	s=request.session
	if yesno == "no":
		s["fulllist"] = "no"
	elif yesno == "yes":
		s["fulllist"] = "yes"
	s.save()
	q=request.url
	ref=request.headers.get('Referer')
	if "/fulllist" in q and yesno == "yes":
		if ref:
			if ref.startswith("http://mimoxindex.com") or ref.startswith("http://www.mimoxindex.com/"):
				#redirect(request.headers.get('Referer'))
				redirect("http://www.mimoxindex.com/1")
			else:
				q=q.split("/fulllist")[0]
				redirect(q)
		else:
			redirect("http://www.mimoxindex.com/1")
	else:
		if ref:
			if ref.startswith("http://mimoxindex.com") or ref.startswith("http://www.mimoxindex.com/"):
				redirect(request.headers.get('Referer'))
		else:
			redirect("http://www.mimoxindex.com/1")
		#q=q.split("/fulllist")[0]
		#redirect(q)

def csvparser(cvfile):
	r="<pre>---------------\nStart CSV parser.\n---------------\n"
	if not cvfile:
		return "<pre>No csvfile given. Exit!\n</pre>"
	csvfile=open(cvfile, 'rb')
	lines=csvfile.readlines()
	csvfile.close()
	csvfile=None
	r+="Open CSVfile: "+cvfile+" ...\n"
	csvfile=open(cvfile, 'rb')
	
	r+=str(len(lines))+" line in csvfile.\n"
	
	if '"' in lines[0] and "," in lines[0]:
		csvcont = csv.reader(csvfile, delimiter=',', quotechar='"')
	elif "," in lines[0]:
		csvcont = csv.reader(csvfile, delimiter=',')
	elif ";" in lines[0]:
		csvcont = csv.reader(csvfile, delimiter=';')
	else:
		csvcont = csv.reader(csvfile)
	
	i=0
	j=0
	
	db,c=MySQLConn()
	
	for row in csvcont:
		
		mimoxid=row[0]
		origtext=row[1]
		enabled=row[2]
		is_default=row[3]
		default_terms_id=row[4]
		if len(row) >= 6:
			mimox_group=row[5]
		else:
			mimox_group=""
		
		if not origtext:
			continue
		
		qry="INSERT INTO `terms` (mimoxid, origtext, enabled, is_default, default_terms_id, mimox_group) VALUES ('%s', '%s', '%s', '%s', '%s', '%s') ON DUPLICATE KEY UPDATE mimoxid='%s', enabled='%s', is_default='%s', default_terms_id='%s', mimox_group='%s'" % (mimoxid, origtext, enabled, is_default, default_terms_id, mimox_group, mimoxid, enabled, is_default, default_terms_id, mimox_group)
		
		#skip first row, its header
		if i > 0:
			try:
				c.execute(qry)
				j+=1
			except Exception,e:
				r+="ERROR in query:"+qry+str(e)+"\n"
				pass
		i+=1
	
	r+=str(j)+" new/updated terms successfully inserted OR updated in database / terms table. \nNew terms will be aapplied to data at next day morning.\n-----------------\n</pre>"
	print r

	u_qry="UPDATE  `mimox_index` SET mimox_group = ( SELECT mimox_group FROM terms WHERE origtext = mimox_index.TermName )"
	c.execute(u_qry)

	closeconn(db,c)
	cmd="rm "+CACHE+"*"
	try:
		os.system(cmd)
	except:
		pass
	return r

def MySQLConn():
	try:
		db=MySQLdb.connect(host=DBHOST,port=3306,user=DBUSER, passwd=DBPASS, db=DBNAME, charset='utf8', init_command='SET NAMES UTF8')
		c=db.cursor()
		return [db,c]
	except Exception,e:
		return [None,None]

def closeconn(*args):
	for i in args:
		try:
			i.close()
		except:
			pass

	
def get_template(tpl=TEMPLATE):
	if os.path.exists(tpl):
		try:
			f=open(tpl,"rb")
			HTML=f.read()
			f.close()
		except:
			HTML = """<h1>Templete error</h1><hr>{{ data }}"""
			pass
	else:
		HTML = """<h1>Templete error</h1><hr>{{ data }}"""
	return HTML

#### auth check here
def check(user, pw):
	s=request.session
	print s
	if "auth" in s:
		if s["auth"] == 1:
			return True
		elif s["auth"] <= -3:
			print s
			return True
	else:
		s["auth"] = 0
	db,c=MySQLConn()
	if not db or not c:
		return (HTML.replace("{{ data }}","MySQL Connection error: "+str(e)))
	pwhash=hashlib.new('sha1')
	pwhash.update(pw)
	pwhash_h=pwhash.hexdigest()
	qry="SELECT 1 FROM admin_users WHERE username = %s AND password = %s"
	c.execute(qry, (user, pwhash_h))
	irows = c.fetchall()
	closeconn(db,c)
	if len(irows):
		s['auth'] = 1
		s['user'] = user
		s.save()
		return True
	else:
		s['auth'] -= 1
		s.save()
		return False
	
@route('/admin')
@auth_basic(check)
def admin():
	s = request.session
	if "auth" in s:
		if s["auth"] <= -3:
			print s
			return "Too much auth request! Please try again later ..."
	HTML=get_template(tpl=ADMIN_TEMPLATE)
	page='<h1><a href="/">MimoxIndex</a> | <a href="/admin">MimoxIndex Admin Pages</a> | Logged in: '+s["user"]+'</h1><hr>'
	page+='<a href="./uploads">Upload MimoxIndex terms CSV into database</a><br>'+"\n"
	return HTML.decode("utf-8").replace("{{ data }}",page)

@route('/uploads')
@route('/uploads/<getfile>', method='GET')
@route('/uploads', method='POST')
@auth_basic(check)
def admin_uploads(getfile=""):
	#check sess
	s = request.session
	if "auth" in s:
		if s["auth"] <= -3:
			return "Too much auth request! Please try again later ..."
	page='<h1><a href="/admin">MimoxIndex Admin Pages</a>  | CSV Uploads | Logged in: '+s["user"]+'</h1>\n'
	
	#handle uploads
	upload = request.files.get('upload')
	if upload:
		now=str(time.strftime("%Y-%m-%d_%H%M%S", time.localtime()))
		name, ext = os.path.splitext(upload.filename)
		if ext not in ('.csv', '.CSV'):
			return "File extension not allowed."
		file_path = "{path}/{file}".format(path=UPLOADS, file=now+"."+upload.filename)
		print file_path
		upload.save(file_path,overwrite=True)
		page+=csvparser(file_path)
	
	#handle downloads
	HTML=get_template(tpl=ADMIN_TEMPLATE)
	onlyfiles = [ f for f in listdir(UPLOADS) if isfile(join(UPLOADS,f)) ]
	onlyfiles = [ f for f in onlyfiles if f.endswith(".csv") ]
	if getfile:
		if getfile in onlyfiles:
			return static_file(getfile, root=UPLOADS, mimetype='text/csv')
	
	page+="<h2>Existing files:</h2>"
	for fil in sorted(onlyfiles):
		c="Uploaded: %s" % time.ctime(os.path.getctime(UPLOADS+'/'+fil))
		page+='<a href="./uploads/'+fil+'">'+fil+'</a> | '+c+'<br>\n'
	page+="<hr>"
	page+="<h2>Upload new file:</h2>"
	page +='''
	<form action="/uploads" method="post" enctype="multipart/form-data"> 
		Select a file: <input type="file" name="upload" />
		<input type="submit" value="Start upload" />
	</form>
	<br>
	'''
	return HTML.decode("utf-8").replace("{{ data }}",page)
	

@route('/')
@route('/<order:int>', method='GET')
def term_rank_data(order='11'):
	
	s = request.session
	
	groupby=0
	try:
		groupby = int(request.query.groupby)
		s["groupby"] = groupby
	except:
		pass
	if not groupby:
		if "groupby" in s:
			groupby = s["groupby"]
	
	print "Request: ",str(request.headers.get('X-Forwarded-For'))
	try:
		order=int(order)
	except:
		order=3
	page='''<table {{class}} width="100%" border="0" cellpadding="2px" cellspacing="0">
	<tbody>
	<tr class="head">{{otb}}
	</tr>\n'''

	qry_dict = request.query.decode()

	searchterm=""
	try:
		if 'searchterm' in qry_dict:
			searchterm = qry_dict['searchterm']
	except Exception,e:
		print e
		pass
		
	termsendid=""
	try:
		if 'termsendid' in qry_dict:
			termsendid = qry_dict['termsendid']
	except Exception,e:
		print e
		pass
	
	print searchterm
	if searchterm:
		order = 1
		groupby=0
	
	fullist_checked=""
	fullist="/fulllist/"
	
	if "fulllist" not in s:
		s["fulllist"] = "no"
		fullist+="yes"
		s.save()
	else:
		if s["fulllist"] == "yes":
			fullist_checked = "checked"
			fullist+="no"
		else:
			fullist_checked = ""
			fullist+="yes"
	chkbox='<br><a href="'+fullist+'" class="order"><span class="level3"><input type="checkbox" '+fullist_checked+' onclick=\'window.location.assign("'+fullist+'")\' value="CheckBox" title="Pipáld ki, ha a teljes listára vagy kiváncsi, egyébként csak az a technológia jelenik meg, amelyiknél legalább egy találat van a hirdetésekben." />Mutasd a teljes listát</span></a>'
	chkbox=chkbox.decode("utf-8")
	
	
	loggedin=False
	if "auth" in s:
		if s["auth"] == 1:
			loggedin = True
	print loggedin
	
	osd={
		1:'Term Name'+chkbox,
		2:'Term Name'+chkbox,
		3:'Current Rank',
		4:'Current Rank',
		5:'Term Count<br>(90days)',
		6:'Term Count<br>(90days)',
		7:'Term Trend<br>(30days)',
		8:'Term Trend<br>(30days)',
		9:'Term Trend<br>(90days)',
		10:'Term Trend<br>(90days)'
	}
	asd={
		'd': '<div class="ascdesc"><img src="/static/s_desc.png" alt="order desc"></div>',
		'a': '<div class="ascdesc"><img src="/static/s_asc.png" alt="order asc"></div>'
	}
	odd={
		1:'<a href="/2" class="order">'+osd[1]+'{{d}}</a>',
		2:'<a href="/1" class="order">'+osd[2]+'{{d}}</a>',
		3:'<a href="/4" class="order">'+osd[3]+'{{d}}</a>',
		4:'<a href="/3" class="order">'+osd[4]+'{{d}}</a>',
		5:'<a href="/6" class="order">'+osd[5]+'{{d}}</a>',
		6:'<a href="/5" class="order">'+osd[6]+'{{d}}</a>',
		7:'<a href="/8" class="order">'+osd[7]+'{{d}}</a>',
		8:'<a href="/7" class="order">'+osd[8]+'{{d}}</a>',
		9:'<a href="10" class="order">'+osd[9]+'{{d}}</a>',
		10:'<a href="/9" class="order">'+osd[10]+'{{d}}</a>'
	}
	odb={
		1:'TermName ASC',
		2:'TermName DESC',
		3:'TermRank ASC',
		4:'TermRank DESC',
		5:'TermCnt ASC',
		6:'TermCnt DESC',
		7:'TermTrend ASC',
		8:'TermTrend DESC',
		9:'TermTrend90 ASC',
		10:'TermTrend90 DESC',
	}
	if order not in odb:
		order=3

	tdhead=""
	for i in xrange(1,6):
		if order:
			#termname
			if i == 1 and order in (1,2):
				if order == 1:
					tdhead+='<td class="head">'+odd[order].replace('{{d}}',asd['a'])+'</td>'
				elif order == 2:
					tdhead+='<td class="head">'+odd[order].replace('{{d}}',asd['d'])+'</td>'
			#current rank
			elif i == 2 and order in (3,4):
				if order == 3:
					tdhead+='<td class="head">'+odd[order].replace('{{d}}',asd['a'])+'</td>'
				elif order == 4:
					tdhead+='<td class="head">'+odd[order].replace('{{d}}',asd['d'])+'</td>'
				
			#term count
			elif i == 3 and order in (5,6):
				if order == 5:
					tdhead+='<td class="head">'+odd[order].replace('{{d}}',asd['a'])+'</td>'
				elif order == 6:
					tdhead+='<td class="head">'+odd[order].replace('{{d}}',asd['d'])+'</td>'
			#term trend 30
			elif i == 4 and order in (7,8):
				if order == 7:
					tdhead+='<td class="head">'+odd[order].replace('{{d}}',asd['a'])+'</td>'
				elif order == 8:
					tdhead+='<td class="head">'+odd[order].replace('{{d}}',asd['d'])+'</td>'
				
			#term trend 90
			elif i == 5 and order in (9,10):
				if order == 9:
					tdhead+='<td class="head">'+odd[order].replace('{{d}}',asd['a'])+'</td>'
				elif order == 10:
					tdhead+='<td class="head">'+odd[order].replace('{{d}}',asd['d'])+'</td>'
			else:
				tdhead+='<td class="head">'+odd[i*2].replace('{{d}}','')+'</td>'
			
		else:
			tdhead+='<td class="head">'+odd[i*2].replace('{{d}}','')+'</td>'
	
	
	#keep here cache handling!
	if CACHE_USE and not loggedin and not searchterm:
		if os.path.exists(CACHE+str(order)+fullist_checked+str(groupby)+str(searchterm)):
			if ((os.path.getmtime(CACHE+str(order)+fullist_checked+str(groupby)+str(searchterm))+CACHE_TIME) > time.time()):
				try:
					f=open(CACHE+str(order)+fullist_checked+str(groupby)+str(searchterm),"rb")
					return f.read()+"\n<!-- served from cache -->"
				except:
					return "<h1>Cache error!</h1>"

	HTML=get_template()
	
	########## MYSQL CONN ##########
	db,c=MySQLConn()
	if not db or not c:
		return (HTML.replace("{{ data }}","MySQL Connection error: "+str(e)))
	
	c_qry="SELECT COUNT(1) FROM `terms` WHERE enabled = 1 AND is_default = 1"
	c.execute(c_qry)
	c_rows = c.fetchall()
	term_cnt=str(c_rows[0][0])
	
	irows=[]
	
	qry="SELECT DISTINCT(mimox_group) FROM mimox_index ORDER BY mimox_group"
	c.execute(qry)
	mimox_groups=c.fetchall()
	
	mimox_groups_o={}
	o=0
	for g in mimox_groups:
		gr=g[0]
		if not gr:
			gr="Any"
		mimox_groups_o[o]=gr
		o+=1
	
	a_group = ""
	if groupby in mimox_groups_o and groupby:
		a_group = "AND mimox_group = '"+mimox_groups_o[groupby]+"' "
	
	if fullist_checked or searchterm:
		if order == 1:
			o="t.origtext"
		else:
			o=odb[order]
		
		searchtermlen=len(searchterm)
		
		if order not in (3,4,5,6):
			if searchterm and searchtermlen > 1:
				st=" AND t.origtext like %s"
			elif searchterm and searchtermlen == 1:
				if searchterm.lower() == "c":
					st=" AND ( t.origtext like 'embedded c%' OR t.origtext = 'c' ) "
				else:
					st=" AND t.origtext = %s"
			else:
				st=" "
			if a_group:
				a_group = a_group.replace("mimox_group","mi.mimox_group")
			#qry="SELECT t.origtext,TermRank,TermCnt,TermTrend,termid,TermTrend90,t.mimoxid FROM `mimox_index` RIGHT JOIN `terms` t ON t.id = termid ORDER BY "+o
			qry="SELECT t.origtext,COALESCE(mi.TermRank,'---'),COALESCE(mi.TermCnt,0),mi.TermTrend,mi.termid,mi.TermTrend90,t.mimoxid FROM `mimox_index` mi RIGHT JOIN `terms` t ON t.id = mi.termid WHERE t.enabled = 1 AND t.is_default = 1 "+st+" "+a_group+" ORDER BY "+o
			if searchterm and searchtermlen > 1:
				c.execute(qry, ('%'+searchterm+'%'))
			elif searchterm and searchtermlen == 1:
				if searchterm.lower() == "c":
					c.execute(qry)
				else:
					c.execute(qry, (searchterm))
			else:
				c.execute(qry)
			irows = c.fetchall()
		else:
			if searchterm and searchtermlen > 1:
				st=" AND TermName like %s"
			elif searchterm and searchtermlen == 1:
				if searchterm.lower() == "c":
					st=" AND ( t.origtext like 'embedded c%' OR t.origtext = 'C' ) "
				else:
					st=" AND TermName = %s"
			else:
				st=" "

			qry="SELECT TermName,TermRank,TermCnt,TermTrend,termid,TermTrend90,mimoxid FROM `mimox_index` WHERE 1 "+a_group+" "+st+" ORDER BY "+odb[order]+""

			if searchterm and searchtermlen > 1:
				c.execute(qry, ('%'+searchterm+'%'))
			elif searchterm and searchtermlen == 1:
				if searchterm.lower() == "c":
					c.execute(qry)
				else:
					c.execute(qry, (searchterm))
			else:
				c.execute(qry)

			irows += c.fetchall()
			
			qry_terms="SELECT id,mimoxid,origtext FROM `terms` WHERE enabled=1 AND is_default=1 ORDER BY origtext"
			c.execute(qry_terms)
			irows_terms = c.fetchall()
			
			should_add_termlist=[]
			found=False
			for t_row in irows_terms:
				mimoxid=int(t_row[1])
				found=any(r_row[6] == mimoxid for r_row in irows)
				if not found:
					#print "NOT Found: ",str(mimoxid),str(t_row[2])
					a=[str(t_row[2]),0,0,0,t_row[0],0,mimoxid]
					if a not in should_add_termlist:
						should_add_termlist.append(a)
			irows=list(irows)
			irows+=should_add_termlist
	else:
		qry="SELECT TermName,TermRank,TermCnt,TermTrend,termid,TermTrend90,mimoxid FROM `mimox_index` WHERE 1 "+a_group+" ORDER BY "+odb[order]+""
		c.execute(qry)
		irows += c.fetchall()
	
	group_form='<select style="padding:0px 0px; width:300px; position:relative; display:inline;" name="groupby" size="1" onchange="if (this.value) window.location.href=this.value">\n'
	for k,v in mimox_groups_o.iteritems():
		if groupby == k:
			selected="selected"
		else:
			selected=""
		group_form+='<option value="/'+str(order)+'?groupby='+str(k)+'" '+selected+'>'+str(v)+'</option>\n'
	group_form+='</select>\n'
	
	if searchterm and not len(irows):
		page = page.replace("{{otb}}","")
		page = page.replace("{{class}}",'')
		qrys="SELECT origtext FROM terms WHERE mimoxid = ( SELECT default_terms_id FROM terms WHERE origtext = %s ) AND enabled = 1 LIMIT 1"
		c.execute(qrys, (searchterm))
		crows = c.fetchall()
		termslist=[]
		for crow in crows:
			termslist.append(crow[0])
		termslist="".join(termslist)
		if len(crows):
			page+='<tr><td colspan="5">A keresett kifejezést: <b>\''+searchterm+'\'</b> már ismerjük, a következő root <b>Term Name</b> alatt használjuk: <b><a href="./?searchterm='+termslist+'">'+termslist+'</a> </td></tr>'
		else:
			need_new_recaptcha=None
			need_form=True
			if termsendid:
				if "termsendid" in s:
					if s["termsendid"] == termsendid:
						if "captcha_challange" in s:
							if s["captcha_challange"] == "OK":
								page+="<b>Az adatokat sikeresen elküldtük, köszönjük a visszajelzést!</b> <br />"
								s["captcha_challange"] == ""
								s["termsendid"] == ""
								s.save()
								page+="<script>$(document).ready(function () { window.setTimeout(function () { location.href = 'http://www.mimoxindex.com'; }, 3000) });</script>"
								need_form=False
							elif s["captcha_challange"] == "NOK":
								page+="<b><font color=\"red\">Sikertelen reCaptcha ellenőrzés (challange error), kérjük próbáld újra!</font></b><br /><br />"
								need_new_recaptcha=True
							else:
								page+="<b>Sikertelen reCaptcha challange (ID), kérjük próbáld újra!</b><br />"
								need_new_recaptcha=True
						else:
							page+="<b>Sikeretelen adatküldés (reCaptcha ellenőrzés) kérjük próbáld újra.</b><br />"
							need_new_recaptcha=True
					else:
						page+="<b>Sikeretelen oldalküldés (session ID), kérjük próbáld újra.</b><br />"
						need_form=False
				else:
					page+="<b>Sikeretelen oldalküldés (POST, URL ID), kérjük próbáld újra.</b><br />"
					need_form=False
			if need_form:
				recaptcha_client = RecaptchaClient(RECAPTCHA_PRIVKEY, RECAPTCHA_PUBKEY)
				if need_new_recaptcha:
					rec=recaptcha_client.get_challenge_markup()
				else:
					rec=recaptcha_client.get_challenge_markup(was_previous_solution_incorrect=True)
				s["termsendid"] = str(uuid.uuid4())
				s.save()
				page+='<tr><td colspan="5">Sikertelen keresés: <b>\''+searchterm+'\'</b><br /><br />Sajnos nincs találat. Ez valószínűleg azt jelenti, hogy vagy nem ismerjük, és ezért nem követjük még. Kisebb százalékban előfordulhat, hogy ismerjük, hallottunk róla, de valamiért úgy döntöttönk, hogy nem vesszük be a listába. Pl. azokat a kifejezéseket, amik inkább jelentenek cégneveket, termékneveket és nem technológiákat, kihúztuk, illetve olyanokat is, mint a HTML, mivel túl sok találat lenne rá, és nem annyira releváns. (A html5 viszont szerepel.) Amennyiben úgy gondolod, hogy jó lenne az általad keresett kifejezést is nyomonkövetnünk, kérjük tegyél javaslatot! Köszönjük!<br /><hr>'
				page +='''
				<form id="submit_term" action="./submit_term/" method="post">
				<table width="100%" border="0" cellpadding="2px" cellspacing="0">
				<input type="hidden" id="termsendid" name="termsendid" value="'''+s["termsendid"]+'''">
				<tr>
				  <td>Kifejezés:</td>
				  <td>&nbsp;<input type="text" name="term" id="term" value="'''+searchterm+'''" >*<br></td>
				</tr>
				<tr>
				  <td>Információ róla a neten:</td>
				  <td>&nbsp;<input type="text" name="info" id="info" title="http://"><br></td>
				</tr>
				<tr>
				  <td>E-mail címed:</td>
				  <td>&nbsp;<input type="text" name="email" id="email" title="e-mail formátum szükséges"  >*</td>
				</tr>
				<tr>
					<td align="left"> <input type="image" src="./static/kuldesgomb_100.png" height="28" alt="Submit Form" /></td>
					<td align="left">
					'''+rec+'''
					</td>
				</tr>
				<tr>
					<td colspan="2">* a mezők kitöltése kötelező</td>
				</tr>
				</table>
				</form>
				'''
			page+='</td></tr>'
	else:
		page = page.replace("{{class}}",'class="level1"')
		page = page.replace("{{otb}}",tdhead)
	
	i=0
	for irow in irows:
		
		if i % 2 == 0:
			trcl="odd"
		else:
			trcl="even"
		TermName=str(irow[0])
		
		TermRank=irow[1]
		if TermRank is None or TermRank == 0:
			TermRank="---"
		
		TermCnt=irow[2]
		if TermCnt is None or TermCnt == 0:
			TermCnt="---"
		
		TermTrend=irow[3]
		if TermTrend is None:
			TermTrend=0
		
		termid=irow[4]
		if termid is None:
			termid=0
		
		TermTrend90=irow[5]
		if TermTrend90 is None:
			TermTrend90=0
		
		mimoxid=irow[6]
		if mimoxid is None:
			mimoxid=0
			
		#img 30
		if TermTrend > 0:
			img='<div class="imgrank"><img src="/static/change_up.png" alt="change_up"></div><div class="rank">+%s</div>' % (TermTrend)
		elif TermTrend < 0:
			img='<div class="imgrank"><img src="/static/change_down.png" alt="change_down"></div><div class="rank">%s</div>' % (TermTrend)
		else:
			img='<div class="imgrank"><img src="/static/nochange.png" alt="nochange"></div><div class="rank">&nbsp;</div>'
			
		#img 90
		if TermTrend90 > 0:
			img90='<div class="imgrank"><img src="/static/change_up.png" alt="change_up"></div><div class="rank">+%s</div>' % (TermTrend90)
		elif TermTrend90 < 0:
			img90='<div class="imgrank"><img src="/static/change_down.png" alt="change_down"></div><div class="rank">%s</div>' % (TermTrend90)
		else:
			img90='<div class="imgrank"><img src="/static/nochange.png" alt="nochange"></div><div class="rank">&nbsp;</div>'
		if not loggedin:
			page+='<tr class="%s"><td class="left"><b>%s<!--%s--></b></td><td class="left">%s</td><td class="left">%s</td><td class="rank">%s</td><td class="rank">%s</td></tr>\n' % (trcl, TermName, termid, TermRank, TermCnt, img, img90)
		else:
			page+='<tr class="%s"><td class="left"><b><a href="/termlist/%s">%s</a></b></td><td class="left">%s</td><td class="left">%s</td><td class="rank">%s</td><td class="rank">%s</td></tr>\n' % (trcl, termid, TermName, TermRank, TermCnt, img, img90)
		i+=1
	page+="</tbody></table>"
	closeconn(db,c)
	R=HTML.decode("utf-8").replace("{{ data }}",page)
	R=R.replace("{{technum}}",term_cnt)
	if not searchterm:
		R=R.replace("{{groupfield}}","Kategória: "+group_form)
	else:
		R=R.replace("{{groupfield}}","")
	R=R.replace("{{searchval}}",searchterm)
	try:
		f=open(CACHE+str(order)+fullist_checked+str(groupby)+str(searchterm),"w")
		f.write(R.encode('utf8'))
		f.close()
	except Exception,e:
		print "Cache write error.", str(e)
		pass
	return(R)

@route('/ajax_termsearch/', method='GET')
def ajax_termsearch(term=""):
	rtr=[]
	term=""
	s = request.session
	term_dict = request.query.decode()
	if 'term' in term_dict:
		term = term_dict['term']
	db,c=MySQLConn()
	if len(term) == 1:
		if term.lower() == 'c':
			qry="SELECT origtext FROM terms WHERE is_default = 1 AND enabled = 1 AND ( origtext = 'c' OR origtext like 'embedded c%') ORDER BY origtext LIMIT 100"
			c.execute(qry)
		else:
			qry="SELECT origtext FROM terms WHERE is_default = 1 AND enabled = 1 AND origtext = %s ORDER BY origtext LIMIT 100"
			c.execute(qry, (term))
	else:
		qry="SELECT origtext FROM terms WHERE is_default = 1 AND enabled = 1 AND origtext like %s ORDER BY origtext LIMIT 100"
		c.execute(qry, ('%'+term+'%'))
	irows = c.fetchall()
	for row in irows:
		rtr.append(row[0])
	closeconn(db,c)
	return json.dumps(rtr)
	
@route('/ajax_gethistory/', method='GET')
def ajax_gethistory(searchdate=""):
	searchdate=""
	s = request.session
	searchdate_dict = request.query.decode()
	if 'searchdate' in searchdate_dict:
		searchdate = searchdate_dict['searchdate']
	db,c=MySQLConn()

	qry="SELECT pickledump FROM trendhisory WHERE trenddate LIKE %s ORDER BY trenddate DESC  LIMIT 1"
	c.execute(qry, (searchdate+'%'))
	irows = c.fetchall()
	pickle_date=pickle.loads(str(irows[0][0]))
	closeconn(db,c)
	return json.dumps(pickle_date)
	
	
@route('/submit_term/', method='POST')
def submit_term():
	s = request.session
	if "termsendid" not in s:
		print "Invalid form term submit!"
		print str(s)
		return "<b>Invalid form</b>"
	ref=request.headers.get('Referer')
	term = request.forms.get('term').decode()
	info = request.forms.get('info').decode()
	email = request.forms.get('email').decode()
	captcha_term = request.forms.get('recaptcha_response_field').decode()
	captcha_id = request.forms.get('recaptcha_challenge_field').decode()
	client_ip = str(request.headers.get('X-Forwarded-For'))
	recaptcha_client = RecaptchaClient(RECAPTCHA_PRIVKEY, RECAPTCHA_PUBKEY)
	captcha_response=None
	try:
		is_solution_correct = recaptcha_client.is_solution_correct(captcha_term,captcha_id,client_ip)
	except Exception as exc:
		print "reCaptcha exception: ",str(exc)
		captcha_response = False
	else:
		if is_solution_correct:
			print "reCaptcha channalnge was OK!",term,info,email,client_ip
			Mailer('uj mimoxindex term','\nTerm name: '+term+'\nInfo: '+info+"\nBekuldo: "+email+"\nclient IP: "+client_ip,"mimoxindex@gmail.com",TERM_EMAIL_RECPS)
			captcha_response = True
		else:
			print "reCaptcha channalnge was false!",term,info,email,client_ip
			captcha_response = False
	if ref:
		if ref.startswith("http://mimoxindex.com") or ref.startswith("http://www.mimoxindex.com/"):
			if captcha_response:
				s["captcha_challange"]="OK"
				s.save()
				redirect("http://www.mimoxindex.com/?searchterm="+term+"&termsendid="+s["termsendid"])
			else:
				s["captcha_challange"]="NOK"
				s.save()
				redirect("http://www.mimoxindex.com/?searchterm="+term+"&termsendid="+s["termsendid"])


#deprecated
@route('/termlist/<jid>', method='GET')
def termlist(jid='1'):
	
	HTML=get_template()
	
	########## MYSQL CONN ##########
	db,c=MySQLConn()
	if not db or not c:
		return (HTML.replace("{{ data }}","MySQL Connection error: "+str(e)))
	qry1="SELECT origtext FROM terms WHERE default_terms_id = ( SELECT mimoxid FROM terms WHERE id = '"+str(int(jid))+"' ) AND enabled = 1 AND is_default = 0"
	c.execute(qry1)
	irows1 = c.fetchall()
	termslist=[]
	for irow1 in irows1:
		termslist.append(irow1[0])
	termslist=" | ".join(termslist)
	if termslist:
		termslist = " | "+termslist
	
	qry="SELECT t.origtext, r.site, r.sitelink, r.title, r.link, r.pubdate, r.description FROM termcount tc LEFT JOIN rss r ON tc.rssid = r.id LEFT JOIN terms t on t.id = tc.termid WHERE t.id = '"+str(int(jid))+"' ORDER BY r.pubdate DESC"
	c.execute(qry)
	irows = c.fetchall()
	
	page="<h2>Jobpost List ("+str(len(irows))+")</h2>"
	page+='<table width="100%" border="0" cellpadding="2px">'
	page+='<tr class="head"><td>'+irows[0][0]+' '+termslist+' </td></tr>\n'
	i=1
	for irow in irows:
		if i % 2 == 0:
			trcl="odd"
		else:
			trcl="even"
		desc=irow[6]
		title=irow[3]
		origtext=irow[0]
		try:
			term=re.escape(origtext)
			term=r'\b%s\b' % (term)
			pattern=re.compile(term,re.IGNORECASE)
			desc=pattern.sub('<font color="red"><b>'+origtext+'</b></font>', desc)
			title=pattern.sub('<font color="red"><b>'+origtext+'</b></font>', title)
		except:
			pass
		jobtxt='<h3>%s. %s</h3><i>%s</i><br /><br /><b>Link: </b><a target="_blank" href="%s">%s</a><br><br> %s <br /> <br /><b>Source: </b><a target="_blank" href="%s">%s</a><br />' % (i, title, irow[5], irow[4], irow[4], desc, irow[2], irow[1])
		page+='<tr class="'+trcl+'"><td>%s</td></tr>\n' % (jobtxt)
		i+=1
	page+="</table>"
	closeconn(db,c)
	return(HTML.decode("utf-8").replace("{{ data }}",page))


@error(404)
def error404(error):
	return '<h1>404 Not Found</h1>'

if __name__=="__main__":
	r=0
	
	#test if already running or not
	try:
		s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
		s.bind((RUN_IP, RUN_PORT))
		s.close()
		r=1
	except Exception, e:
		print "Already running:", str(e)
		sys.exit(1)
	if r:
		run(server='bjoern', host=RUN_IP, port=RUN_PORT, debug=True, reloader=False, app=app)
