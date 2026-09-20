import http from 'node:http';
import { randomBytes, createHash } from 'node:crypto';
import { readFile, writeFile } from 'node:fs/promises';
import { spawn } from 'node:child_process';

const clientId=process.env.GOOGLE_CLIENT_ID,clientSecret=process.env.GOOGLE_CLIENT_SECRET;
if(!clientId||!clientSecret){console.error('.envにGOOGLE_CLIENT_IDとGOOGLE_CLIENT_SECRETを設定してください。');process.exit(1);}
const redirect='http://127.0.0.1:8792/callback',state=randomBytes(24).toString('hex'),verifier=randomBytes(48).toString('base64url');
const auth=new URL('https://accounts.google.com/o/oauth2/v2/auth');
auth.search=new URLSearchParams({client_id:clientId,redirect_uri:redirect,response_type:'code',access_type:'offline',prompt:'consent',state,code_challenge:createHash('sha256').update(verifier).digest('base64url'),code_challenge_method:'S256',scope:'https://www.googleapis.com/auth/calendar.freebusy https://www.googleapis.com/auth/calendar.events'}).toString();
const server=http.createServer(async(req,res)=>{
  const url=new URL(req.url,redirect);
  res.setHeader('Content-Type','text/plain; charset=utf-8');res.setHeader('Cache-Control','no-store');
  if(url.pathname!=='/callback'){res.writeHead(404);res.end();return;}
  if(url.searchParams.get('state')!==state){res.writeHead(403);res.end('認証をやり直してください。');return;}
  if(!url.searchParams.get('code')){res.writeHead(400);res.end('接続が許可されませんでした。');return;}
  try{
    const response=await fetch('https://oauth2.googleapis.com/token',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({client_id:clientId,client_secret:clientSecret,redirect_uri:redirect,grant_type:'authorization_code',code:url.searchParams.get('code'),code_verifier:verifier}),signal:AbortSignal.timeout(15000)});
    const result=await response.json();
    if(!response.ok||!result.refresh_token)throw new Error('認証できませんでした。');
    let content=await readFile('private/.env','utf8');
    const line='GOOGLE_REFRESH_TOKEN='+result.refresh_token;
    content=/^GOOGLE_REFRESH_TOKEN=.*$/m.test(content)?content.replace(/^GOOGLE_REFRESH_TOKEN=.*$/m,line):content+'\n'+line+'\n';
    await writeFile('private/.env',content,{mode:0o600});
    res.end('Googleカレンダーの認証情報をMacの非公開設定に保存しました。このタブを閉じて、Codexに「接続できた」と伝えてください。');
    console.log('Google認証完了。トークンは.envに保存しました。表示しません。');
    server.close();
  }catch{res.writeHead(502);res.end('接続に失敗しました。Googleの設定を確認して、やり直してください。');}
});
server.listen(8792,'127.0.0.1',()=>{console.log('Googleの認証画面を開きます。');spawn('open',[auth.toString()],{stdio:'ignore'});});
setTimeout(()=>{server.close();console.log('認証待機を終了しました。');},10*60000).unref();
