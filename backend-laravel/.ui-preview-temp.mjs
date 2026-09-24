import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
const build=path.resolve('public/build');
const manifest=JSON.parse(fs.readFileSync(path.join(build,'manifest.json'),'utf8'));
const entry=manifest['resources/js/main.ts'];
const record={id:7,numero:'001-2026',materia:'Expediente ficticio de prueba',juzgado:'Juzgado de prueba',especialista:'',tercero:'',demandante:'Persona de prueba A',demandado:'Persona de prueba B',estado:'En trámite',archivo:false,nombre_archivo:null,ultima_resolucion:1,resolucion_detectada:1,created_at:'2026-09-23',updated_at:'2026-09-23'};
const editor={expediente_id:7,resolucion_id:2,numero:2,estado:'pendiente',document_name:'Resolución 2 — expediente 001-2026',header:[],header_data:{numero:record.numero,materia:record.materia,juzgado:record.juzgado,especialista:'',tercero:'',demandado:record.demandado,demandante:record.demandante},content:{type:'doc',content:[{type:'paragraph',content:[{type:'text',text:'Contenido ficticio para revisar la interfaz del editor.'}]}]},version:0,saved_at:null};
const server=http.createServer((req,res)=>{
 const url=new URL(req.url,'http://127.0.0.1');
 const json=value=>{res.setHeader('Content-Type','application/json');res.end(JSON.stringify(value));};
 if(url.pathname.startsWith('/api/')){
   if(url.pathname==='/api/auth/login') return json({user:{id:1,nombre:'Prueba local',username:'prueba',rol:{id:2,nombre:'USUARIO'}},access_token:'fixture-local-only'});
   if(url.pathname==='/api/auth/logout') return json({});
   if(url.pathname==='/api/expedientes') return json([record]);
   if(url.pathname==='/api/expedientes/7') return json(record);
   if(url.pathname.endsWith('/resoluciones')) return json({ultima_resolucion:1,resolucion_detectada:1,resoluciones:[]});
   if(url.pathname.endsWith('/editor')) return json(editor);
   res.statusCode=404;return json({message:'Ruta no incluida en la prueba visual'});
 }
 if(url.pathname.startsWith('/build/')){
  const file=path.resolve(build,url.pathname.slice(7));
  if(!file.startsWith(build+path.sep)||!fs.existsSync(file)){res.statusCode=404;return res.end();}
  res.setHeader('Content-Type',file.endsWith('.css')?'text/css':'text/javascript');return fs.createReadStream(file).pipe(res);
 }
 res.setHeader('Content-Type','text/html; charset=utf-8');
 res.end('<!doctype html><html lang="es"><head><meta name="viewport" content="width=device-width, initial-scale=1"><title>Prueba local de interfaz</title>'+entry.css.map(css=>'<link rel="stylesheet" href="/build/'+css+'">').join('')+'</head><body><div id="app"></div><script type="module" src="/build/'+entry.file+'"></script></body></html>');
});
server.listen(4179,'127.0.0.1',()=>console.log('Prueba ficticia: http://127.0.0.1:4179'));

