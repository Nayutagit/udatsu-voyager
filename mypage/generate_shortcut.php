<?php
/**
 * generate_shortcut.php
 * Serves a personalized Apple Shortcut (.shortcut) file
 * pre-configured with the user's API token.
 * 
 * User just downloads → opens on iPhone → installs → appears in Share Sheet
 */
require_once __DIR__ . '/../core/bootstrap.php';

if (empty($uid)) {
    header('Location: ../index.php');
    exit();
}

$profileFile = __DIR__ . '/../users/' . $uid . '_profile.json';
$profile     = file_exists($profileFile) ? json_decode(file_get_contents($profileFile), true) : [];
$apiToken    = $profile['api_token'] ?? null;

if (!$apiToken) {
    header('Location: shortcut_setup.php?error=no_token');
    exit();
}

$uploadUrl = 'https://udatsu-voyager.com/api_upload.php?token=' . $apiToken;

// Apple Shortcut XML plist with the token embedded
// This shortcut:
// 1. Accepts audio files from the Share Sheet
// 2. POSTs them as multipart/form-data to the upload URL
// 3. Shows a notification when done
$plist = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
	<key>WFWorkflowClientVersion</key>
	<string>1240.0.1</string>
	<key>WFWorkflowHasOutputFallback</key>
	<false/>
	<key>WFWorkflowIcon</key>
	<dict>
		<key>WFWorkflowIconGlyphNumber</key>
		<integer>59697</integer>
		<key>WFWorkflowIconStartColor</key>
		<integer>990537472</integer>
	</dict>
	<key>WFWorkflowImportQuestions</key>
	<array/>
	<key>WFWorkflowMinimumClientVersion</key>
	<integer>900</integer>
	<key>WFWorkflowMinimumClientVersionString</key>
	<string>900</string>
	<key>WFWorkflowOutputContentItemClasses</key>
	<array/>
	<key>WFWorkflowTypes</key>
	<array>
		<string>ActionExtension</string>
	</array>
	<key>WFWorkflowActions</key>
	<array>
		<dict>
			<key>WFWorkflowActionIdentifier</key>
			<string>is.workflow.actions.request</string>
			<key>WFWorkflowActionParameters</key>
			<dict>
				<key>WFHTTPMethod</key>
				<string>POST</string>
				<key>WFHTTPBodyType</key>
				<string>Form</string>
				<key>WFURL</key>
				<string>{$uploadUrl}</string>
				<key>WFFormValues</key>
				<dict>
					<key>Value</key>
					<dict>
						<key>WFDictionaryFieldValueItems</key>
						<array>
							<dict>
								<key>WFItemType</key>
								<integer>0</integer>
								<key>WFKey</key>
								<dict>
									<key>Value</key>
									<dict>
										<key>string</key>
										<string>audioFile</string>
									</dict>
									<key>WFSerializationType</key>
									<string>WFTextTokenString</string>
								</dict>
								<key>WFValue</key>
								<dict>
									<key>Value</key>
									<dict>
										<key>Type</key>
										<string>ExtensionInput</string>
									</dict>
									<key>WFSerializationType</key>
									<string>WFTextTokenAttachmentParameterState</string>
								</dict>
							</dict>
						</array>
					</dict>
					<key>WFSerializationType</key>
					<string>WFDictionaryFieldValue</string>
				</dict>
			</dict>
		</dict>
		<dict>
			<key>WFWorkflowActionIdentifier</key>
			<string>is.workflow.actions.notification</string>
			<key>WFWorkflowActionParameters</key>
			<dict>
				<key>WFNotificationActionTitle</key>
				<string>🎙 Voyagerに送信完了！</string>
				<key>WFNotificationActionBody</key>
				<dict>
					<key>Value</key>
					<dict>
						<key>string</key>
						<string>音声解析中です。数分後にダッシュボードをご確認ください。</string>
					</dict>
					<key>WFSerializationType</key>
					<string>WFTextTokenString</string>
				</dict>
			</dict>
		</dict>
	</array>
</dict>
</plist>
XML;

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="Udatsuに送る.shortcut"');
header('Content-Length: ' . strlen($plist));
header('Cache-Control: no-cache');
echo $plist;
exit();
