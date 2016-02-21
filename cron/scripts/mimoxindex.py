#!/usr/bin/python -u
# -*- coding: utf-8 -*-

import csv
import MySQLdb
import os,sys
import hashlib
import time
import feedparser
import re
import cPickle as pickle

class MimoxIndex():
	
	def __init__(self,trendcount=None,csvfile=None):
		self.BASEDIR=os.environ.get('BASEDIR')
		self.DBHOST=os.environ.get('DBHOST')
		self.DBUSER=os.environ.get('DBUSER')
		self.DBPASS=os.environ.get('DBPASS')
		self.DBNAME=os.environ.get('DBNAME')
		self.ALTA_DBHOST=os.environ.get('ALTA_DBHOST')
		self.ALTA_DBUSER=os.environ.get('ALTA_DBUSER')
		self.ALTA_DBPASS=os.environ.get('ALTA_DBPASS')
		self.ALTA_DBNAME=os.environ.get('ALTA_DBNAME')
		self.MAXTIME_MONTH=3
		self.THREE_MONTHS_OLD=str(time.strftime('%Y-%m-%d %H:%M:%S',time.localtime(time.time()-(self.MAXTIME_MONTH*30*24*60*60))))
		self.NOW=str(time.strftime('%Y-%m-%d %H:%M:%S',time.localtime()))
		self.CHANGE_COMPARE_DAYS=30
		self.DEBUG=1
		self.db=None
		self.c=None
		self.alta_db=None
		self.alta_c=None
		self.csvfile=csvfile
		self.trendcount=trendcount
		self.main()
		
	def main(self):
		self.initMysqlConns()
		self.maintainer()
		if self.csvfile:
			self.csvparser()
			self.closeMysqlConn()
			return
		elif self.trendcount == "trendcount":
			self.trendcounter()
			self.trendcounter90()
			self.altaexport()
			return
		elif self.trendcount == "clean":
			self.clean()
			return
		elif self.trendcount == "cleanall":
			self.clean('all')
			return
		elif self.trendcount == "cleanterms":
			self.clean('terms')
			return
		elif self.trendcount == "altaexport":
			self.altaexport()
			return
		self.rssparser()
		self.termcounter()
		self.ranker()
		self.altaexport()
		
	def initMysqlConns(self):
		try:
			self.db=MySQLdb.connect(host=self.DBHOST,port=3306,user=self.DBUSER, passwd=self.DBPASS, db=self.DBNAME, charset='utf8', init_command='SET NAMES UTF8')
			self.c=self.db.cursor()
			print "Connected to local MySQL..."
		except Exception,e:
			print str(e),". Exit!"
			sys.exit(1)
		try:
			self.alta_db=MySQLdb.connect(host=self.ALTA_DBHOST,port=3306,user=self.ALTA_DBUSER, passwd=self.ALTA_DBPASS, db=self.ALTA_DBNAME, charset='utf8', init_command='SET NAMES UTF8')
			self.alta_c=self.alta_db.cursor()
			print "Connected to Altadb MySQL..."
		except Exception,e:
			print e
			pass
			
	def closeMysqlConn(self):
		try:
			self.db.close()
		except:
			pass
		try:
			self.alta_db.close()
		except:
			pass
			
	def csvparser(self):
		print "csvparser..."
		if not self.csvfile:
			print "No csvfile given. Exit!"
			return
		csvfile=open(self.csvfile, 'rb')
		lines=csvfile.readlines()
		csvfile.close()
		csvfile=None
		csvfile=open(self.csvfile, 'rb')
		print str(len(lines))," in csvfile."
		if '"' in lines[0] and "," in lines[0]:
			csvcont = csv.reader(csvfile, delimiter=',', quotechar='"')
		elif "," in lines[0]:
			csvcont = csv.reader(csvfile, delimiter=',')
		elif ";" in lines[0]:
			csvcont = csv.reader(csvfile, delimiter=';')
		else:
			csvcont = csv.reader(csvfile)
		i=0
		print csvcont
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
			if i > 0:
				try:
					self.c.execute(qry)
				except Exception,e:
					print qry, str(e)
					pass
			i+=1
		print "Terms inserted/updated to terms table: ",str(i)

	def rssparser(self):
		print "rssparser..."
		fs=[]
		d=os.listdir(self.BASEDIR)
		for f in d:
			if f.lower().endswith("rss.xml".lower()):
				fs.append(self.BASEDIR+"/"+f)
		for feedfile in fs:
			d = feedparser.parse(feedfile)
			if d['bozo']:
				print "RSS Error!. Skip:", feedfile
				continue
			feedlink=d['feed']['link']
			print "Parse ", feedfile, feedlink
			i=0
			for k in d['entries']:
				description=k['summary_detail']['value'].replace("'","").replace('"',"")
				link=k['link']
				title=k['title'].replace("'","").replace('"',"")
				author=k['author']
				pubdate=str(k['published'])
				h=hashlib.new('sha1')
				
				#hash: site+title+description
				ht="%s%s%s" % (author.encode('utf-8'),title.encode('utf-8'),description.encode('utf-8'))
				h.update(ht)
				hsh=h.hexdigest()
				
				#check existing link
				if link and link != feedlink:
					qry_check="SELECT id, link FROM `rss` WHERE link = '%s'" % (link)
					self.c.execute(qry_check)
					chrows = self.c.fetchall()
					if len(chrows):
						print "Link already exist in DB, skip: ",link,feedlink,str(chrows)
						continue
				
				#insert qry
				qry="INSERT INTO `rss` (site, sitelink, title, link, pubdate, description, hash) VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s') ON DUPLICATE KEY UPDATE site = '%s', sitelink='%s', title='%s', link='%s', description = '%s' " % (author, feedlink, title, link, pubdate, description, hsh, author, feedlink, title, link, description)
				
				try:
					#print qry
					self.c.execute(qry)
					i+=1
				except Exception,e:
					print str(e)
					print "Qry:", qry
					pass
				
				#print qry
			print "Entries: ",str(len(d['entries'])),", Inserted NEW: ",str(i)
			print "---"
			
	def termcounter(self):
		self.closeMysqlConn()
		self.initMysqlConns()
		print "termcounter..."
		print "Select core terms..."
		## terms
		qry = "SELECT id,origtext,mimoxid FROM `terms` WHERE enabled='1' AND is_default='1' AND default_terms_id='0' "
		self.c.execute(qry)
		crows = self.c.fetchall()
		terms={}
		
		for row in crows:
			tid=str(row[0])
			origtext=str(row[1])
			mimoxid=str(row[2])
			aqry = "SELECT id,origtext FROM `terms` WHERE enabled='1' AND is_default='0' AND default_terms_id='%s'" % (mimoxid)
			self.c.execute(aqry)
			arows = self.c.fetchall()
			astr=""
			#print arows
			for arow in arows:
				aid=str(arow[0])
				atext=str(arow[1])
				if atext:
					astr+="|"+atext
			terms[tid]=origtext+astr

		print "Core terms: ",str(len(terms)),str(terms)

		## rss contents
		qry = "SELECT id,title,description FROM `rss`"
		self.c.execute(qry)
		rrows = self.c.fetchall()
		rss={}
		for rrow in rrows:
			rid=str(rrow[0])
			title=rrow[1]
			description=rrow[2]
			rss[rid]=[title,description]

		print "RSS: ",str(len(rss))

		## recursive find
		i=0
		for rid,txtar in rss.iteritems():
			for tid,term in terms.iteritems():
				
				txt=" ".join(txtar)
				title=txtar[0]
				description=txtar[1]
				lterm=term.lower()
				ltxt=txt.lower()
				
				#print term
				found=False
				#check for +, #
				if "|" not in term:
					if ("+" in lterm) or ("#" in lterm) or (lterm == 'com'):
						if lterm == 'com':
								if ' COM '  in txt:
									found=True
						elif lterm in ltxt:
							found=True
					elif len(term) == 1:
						if ((" "+term+" ") in txt) or ((" "+term+" ") in description) or (("/"+term+" ") in txt) or ((" "+term+"/") in txt) or (("/"+term+" ") in description) or ((" "+term+"/") in description):
							found=True
						#elif ("+" not in description) and ("#" not in description):
						#	term=re.escape(term)
						#	term=r'\b%s\b' % (term)
						#	#print "1", term
						#	p=re.compile(term,re.UNICODE)
						#	if p.search(description):
						#		found=True
					else:
						o_term=term
						term=r'\b%s\b' % (term)
						p=re.compile(term,re.UNICODE|re.IGNORECASE)
						if p.search(description):
							found=True
				else:
					if "#" not in term:
						locterms=term.split("|")
						locterms=map(re.escape,locterms)
						term="\\b|\\b".join(locterms)
						term="\\b"+term
						term=term+"\\b"
					else:
						locterms=term.split("|")
						locterms=map(re.escape,locterms)
						term="|".join(locterms)
					#/build term
					p=re.compile(term,re.UNICODE|re.IGNORECASE)
					if p.search(txt):
						found=True
				
				#insert if found something
				if  found:
					iqry="INSERT INTO `termcount` (termid, rssid) VALUES ('%s', '%s')" % (tid,rid)
					#print str(tid), term, iqry
					try:
						self.c.execute(iqry)
						i+=1
					except Exception,e:
#						print str(e), iqry
						pass
				term=None
				p=None
			print str(len(rss)),"/",str(i)
				## old:
#				lterm=term.lower()
#				ltxt=txt.lower()
#				if lterm in ltxt:
#					iqry="INSERT INTO `termcount` (termid, rssid) VALUES ('%s', '%s')" % (tid,rid)
#					try:
#						self.c.execute(iqry)
#						i+=1
#					except:
#						pass

		print "Terms inserted: ",str(i)
		
	def ranker(self):
		self.closeMysqlConn()
		self.initMysqlConns()

		print "ranker..."

		## ranks
		qry = "SELECT count(tc.termid) as cnt, t.origtext, t.mimoxid, t.id, t.mimox_group FROM termcount tc  LEFT JOIN terms t ON t.id = tc.termid WHERE t.enabled = '1' GROUP BY t.id ORDER BY cnt DESC"

		self.c.execute(qry)
		crows = self.c.fetchall()
		
		terms={}
		
		r=1
		i=0
		for row in crows:
			termcnt=str(row[0])
			termname=str(row[1])
			mimoxid=str(row[2])
			termid=str(row[3])
			mimox_group=str(row[4])
			aqry = "SELECT id,origtext FROM `terms` WHERE enabled='1' AND is_default='0' AND default_terms_id='%s'" % (mimoxid)
			self.c.execute(aqry)
			arows = self.c.fetchall()
			astr=[]
			#print arows
			for arow in arows:
				aid=str(arow[0])
				atext=str(arow[1])
				if atext:
					astr.append(atext)
			astr=", ".join(astr)
			uqry="INSERT INTO `mimox_index` (mimoxid,termid,TermName,TermSearchName,TermFirstDate,TermCnt,TermRank,TermRankDate,mimox_group) VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s','%s') ON DUPLICATE KEY UPDATE mimoxid = '%s', TermCnt='%s', TermRank='%s', TermRankDate='%s', TermName='%s', TermSearchName='%s', mimox_group='%s'" % (mimoxid, termid, termname, astr, self.NOW, termcnt, r, self.NOW, mimox_group, mimoxid, termcnt, r, self.NOW, termname, astr, mimox_group)
			
			terms[termname]=[termcnt,r]
			
			try:
				self.c.execute(uqry)
				i+=1
			except:
				pass
			r+=1
		
		print "Updated current termcount: ", str(r), str(i)
		print "Insert trendhistory..."
		ds=pickle.dumps(terms)
		hqry="""INSERT INTO `trendhisory` (trenddate,pickledump) VALUES ("%s","%s") """ % (self.NOW, ds)
		try:
			self.c.execute(hqry)
			print "Inserted trendhisory..."
		except:
			pass
		
	def trendcounter(self):
		self.closeMysqlConn()
		self.initMysqlConns()

		qry="""SELECT trenddate, pickledump 
				FROM `trendhisory` AS a, (SELECT MIN(trenddate) AS mini, MAX(trenddate) AS maxi FROM `trendhisory` WHERE trenddate > NOW()-INTERVAL """+str(self.CHANGE_COMPARE_DAYS)+""" DAY) AS m
				WHERE  m.maxi = a.trenddate
				OR m.mini = a.trenddate ORDER BY trenddate"""
		self.c.execute(qry)
		crows = self.c.fetchall()
		if not len(crows) == 2:
			print "crows != 2, exit."
			return
		min_row=crows[0]
		max_row=crows[1]
		min_date=str(min_row[0])
		min_pickle=pickle.loads(str(min_row[1]))
		max_date=str(max_row[0])
		max_pickle=pickle.loads(str(max_row[1]))
		for i,j in max_pickle.iteritems():
			max_termcnt=j
			if i in min_pickle:
				min_termcnt=min_pickle[i]
				cur_trend=min_termcnt[1]-max_termcnt[1]
				print min_date, i, " / ",str(min_termcnt[1]), " => ", max_date, " / ",str(max_termcnt[1]), "\t change:", str(cur_trend)
				if not cur_trend:
					cur_trend=0
				uqry="UPDATE `mimox_index` SET TermTrend = '%s', TermTrendChangeDate='%s' WHERE TermName = '%s'" % (cur_trend, self.NOW, i)
				try:
					self.c.execute(uqry)
				except Exception,e:
					print e
					pass
		
	def trendcounter90(self):
		self.closeMysqlConn()
		self.initMysqlConns()

		qry="""SELECT trenddate, pickledump 
				FROM `trendhisory` AS a, (SELECT MIN(trenddate) AS mini, MAX(trenddate) AS maxi FROM `trendhisory` WHERE trenddate > NOW()-INTERVAL """+str(3*self.CHANGE_COMPARE_DAYS)+""" DAY) AS m
				WHERE  m.maxi = a.trenddate
				OR m.mini = a.trenddate ORDER BY trenddate"""
		self.c.execute(qry)
		crows = self.c.fetchall()
		if not len(crows) == 2:
			print "crows != 2, exit."
			return
		min_row=crows[0]
		max_row=crows[1]
		min_date=str(min_row[0])
		min_pickle=pickle.loads(str(min_row[1]))
		max_date=str(max_row[0])
		max_pickle=pickle.loads(str(max_row[1]))
		for i,j in max_pickle.iteritems():
			max_termcnt=j
			if i in min_pickle:
				min_termcnt=min_pickle[i]
				cur_trend=min_termcnt[1]-max_termcnt[1]
				print min_date, i, " / ",str(min_termcnt[1]), " => ", max_date, " / ",str(max_termcnt[1]), "\t change:", str(cur_trend)
				if not cur_trend:
					cur_trend=0
				uqry="UPDATE `mimox_index` SET TermTrend90 = '%s', TermTrendChangeDate90='%s' WHERE TermName = '%s'" % (cur_trend, self.NOW, i)
				try:
					self.c.execute(uqry)
				except Exception,e:
					print e
					pass
		
	def maintainer(self):
		self.closeMysqlConn()
		self.initMysqlConns()

		print "maintainer..."

		#maintain rss
		qry1="DELETE FROM `rss` WHERE pubdate < '%s'" % (self.THREE_MONTHS_OLD)
		print qry1
		try:
			self.c.execute(qry1)
		except Exception,e:
			print e
			pass

		#maintain termcount
		qry1="DELETE FROM `termcount` WHERE termid IN (SELECT id from `terms` WHERE enabled <> '1')"
		print qry1
		try:
			self.c.execute(qry1)
		except Exception,e:
			print e
			pass

			
		qry1="DELETE FROM `termcount` WHERE rssid NOT IN (SELECT id from `rss`)"
		print qry1
		try:
			self.c.execute(qry1)
		except Exception,e:
			print e
			pass

		#maintain mimox_index
		qry1="DELETE FROM `mimox_index` WHERE TermFirstDate < '%s'" % (self.THREE_MONTHS_OLD)
		print qry1
		try:
			self.c.execute(qry1)
		except Exception,e:
			print e
			pass

		#delete where not enabled
		qry1="DELETE FROM `mimox_index` WHERE termid IN (SELECT id from `terms` WHERE enabled <> '1')"
		print qry1
		try:
			self.c.execute(qry1)
		except Exception,e:
			print e
			pass
			
			
	def clean(self,all=''):
		self.closeMysqlConn()
		self.initMysqlConns()

		print "cleaner..."
		#maintain termcount

		if all == "terms":
			qry1="TRUNCATE `terms`"
			print qry1
			try:
				self.c.execute(qry1)
			except Exception,e:
				print e
				pass
			return

		qry1="TRUNCATE `termcount`"
		print qry1
		try:
			self.c.execute(qry1)
		except Exception,e:
			print e
			pass
			
		#qry1="TRUNCATE `rss`"
		#print qry1
		#try:
		#	self.c.execute(qry1)
		#except Exception,e:
		#	print e
		#	pass
			
		qry1="TRUNCATE `mimox_index`"
		print qry1
		try:
			self.c.execute(qry1)
		except Exception,e:
			print e
			pass
		if all:
			qry1="TRUNCATE `trendhisory`"
			print qry1
			try:
				self.c.execute(qry1)
			except Exception,e:
				print e
				pass

			
		if not self.alta_db:
			print "No ALTADB connection. Exit."
			return
		qry1="TRUNCATE `mimox_index`"
		print qry1
		try:
			self.alta_c.execute(qry1)
		except Exception,e:
			print e
			pass
		
	def altaexport(self):
		self.closeMysqlConn()
		self.initMysqlConns()
		print "altaexport..."
		if not self.alta_db:
			print "No ALTADB connection. Exit."
			return
		## upload data
		#SELECT TermName,TermRank,TermCnt,TermTrend,termid FROM `mimox_index` ORDER BY TermRank ASC
		qry1="SELECT mimoxid,TermName,TermRank,TermCnt,TermTrend,TermRankDate FROM `mimox_index` ORDER BY TermRank ASC"
		self.c.execute(qry1)
		irows = self.c.fetchall()
		for irow in irows:
			mimoxid=str(irow[0])
			TermName=str(irow[1])
			TermRank=str(irow[2])
			TermCnt=str(irow[3])
			TermTrend=str(irow[4])
			TermRankDate=str(irow[5])[:10]
			if len(TermRankDate) < 10:
				TermRankDate=self.NOW[:10]
			aqry1="SELECT 1 FROM `mimox_index` WHERE TermName = '%s'" % (TermName)
			shouldinsert=True
			try:
				self.alta_c.execute(aqry1)
				arows = self.alta_c.fetchall()
				if len(arows):
					shouldinsert=False
			except Exception,e:
				print e
				print aqry1
				pass
			if shouldinsert:
				aqry2="INSERT INTO `mimox_index` (MimoxIndexId,TermName,TermRank,TermCount,TermTrend,ImportDate) VALUES ('%s', '%s', '%s', '%s', '%s', '%s')" % (mimoxid, TermName, TermRank, TermCnt, TermTrend, TermRankDate)
				print aqry2
			else:
				aqry2="UPDATE `mimox_index` SET TermRank='%s', TermCount='%s', TermTrend='%s', ImportDate='%s' WHERE TermName = '%s'" % (TermRank, TermCnt, TermTrend, TermRankDate, TermName)
				print aqry2
			try:
				self.alta_c.execute(aqry2)
			except Exception,e:
				print e
				print aqry2
				pass

		## maintain
		#maintain mimox_index
		aqry3="DELETE FROM `mimox_index` WHERE ImportDate < '%s'" % (self.THREE_MONTHS_OLD[:10])
		print aqry3
		try:
			self.alta_c.execute(aqry3)
		except Exception,e:
			print e
			pass

############################
# start main program
############################
if __name__=="__main__":
	print "Main: START Application "+sys.argv[0]+" ..."
	print '---------------------'
	
	f=None
	try:
		f=sys.argv[1]
	except:
		pass
	
	if f:
		if f == "trendcount" or f == "clean" or f == "cleanall" or f == "cleanterms" or f == "altaexport":
			MimoxIndex(f,None)
		else:
			print "For trendcount use: [trendcount] ..."
			print "Try to open file for CSV parsing..."
			MimoxIndex(None,f)
	else:
		MimoxIndex()
	
	print "DONE."
