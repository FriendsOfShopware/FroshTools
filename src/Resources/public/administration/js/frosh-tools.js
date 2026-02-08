(()=>{var te=Object.create;var I=Object.defineProperty;var se=Object.getOwnPropertyDescriptor;var ae=Object.getOwnPropertyNames;var ie=Object.getPrototypeOf,re=Object.prototype.hasOwnProperty;var oe=(e,t)=>()=>(t||e((t={exports:{}}).exports,t),t.exports);var ne=(e,t,s,a)=>{if(t&&typeof t=="object"||typeof t=="function")for(let r of ae(t))!re.call(e,r)&&r!==s&&I(e,r,{get:()=>t[r],enumerable:!(a=se(t,r))||a.enumerable});return e};var le=(e,t,s)=>(s=e!=null?te(ie(e)):{},ne(t||!e||!e.__esModule?I(s,"default",{value:e,enumerable:!0}):s,e));var X=oe((xt,D)=>{var h=function(){this.Diff_Timeout=1,this.Diff_EditCost=4,this.Match_Threshold=.5,this.Match_Distance=1e3,this.Patch_DeleteThreshold=.5,this.Patch_Margin=4,this.Match_MaxBits=32},b=-1,_=1,m=0;h.Diff=function(e,t){return[e,t]};h.prototype.diff_main=function(e,t,s,a){typeof a>"u"&&(this.Diff_Timeout<=0?a=Number.MAX_VALUE:a=new Date().getTime()+this.Diff_Timeout*1e3);var r=a;if(e==null||t==null)throw new Error("Null input. (diff_main)");if(e==t)return e?[new h.Diff(m,e)]:[];typeof s>"u"&&(s=!0);var i=s,o=this.diff_commonPrefix(e,t),n=e.substring(0,o);e=e.substring(o),t=t.substring(o),o=this.diff_commonSuffix(e,t);var l=e.substring(e.length-o);e=e.substring(0,e.length-o),t=t.substring(0,t.length-o);var c=this.diff_compute_(e,t,i,r);return n&&c.unshift(new h.Diff(m,n)),l&&c.push(new h.Diff(m,l)),this.diff_cleanupMerge(c),c};h.prototype.diff_compute_=function(e,t,s,a){var r;if(!e)return[new h.Diff(_,t)];if(!t)return[new h.Diff(b,e)];var i=e.length>t.length?e:t,o=e.length>t.length?t:e,n=i.indexOf(o);if(n!=-1)return r=[new h.Diff(_,i.substring(0,n)),new h.Diff(m,o),new h.Diff(_,i.substring(n+o.length))],e.length>t.length&&(r[0][0]=r[2][0]=b),r;if(o.length==1)return[new h.Diff(b,e),new h.Diff(_,t)];var l=this.diff_halfMatch_(e,t);if(l){var c=l[0],d=l[1],f=l[2],u=l[3],p=l[4],g=this.diff_main(c,f,s,a),v=this.diff_main(d,u,s,a);return g.concat([new h.Diff(m,p)],v)}return s&&e.length>100&&t.length>100?this.diff_lineMode_(e,t,a):this.diff_bisect_(e,t,a)};h.prototype.diff_lineMode_=function(e,t,s){var a=this.diff_linesToChars_(e,t);e=a.chars1,t=a.chars2;var r=a.lineArray,i=this.diff_main(e,t,!1,s);this.diff_charsToLines_(i,r),this.diff_cleanupSemantic(i),i.push(new h.Diff(m,""));for(var o=0,n=0,l=0,c="",d="";o<i.length;){switch(i[o][0]){case _:l++,d+=i[o][1];break;case b:n++,c+=i[o][1];break;case m:if(n>=1&&l>=1){i.splice(o-n-l,n+l),o=o-n-l;for(var f=this.diff_main(c,d,!1,s),u=f.length-1;u>=0;u--)i.splice(o,0,f[u]);o=o+f.length}l=0,n=0,c="",d="";break}o++}return i.pop(),i};h.prototype.diff_bisect_=function(e,t,s){for(var a=e.length,r=t.length,i=Math.ceil((a+r)/2),o=i,n=2*i,l=new Array(n),c=new Array(n),d=0;d<n;d++)l[d]=-1,c[d]=-1;l[o+1]=0,c[o+1]=0;for(var f=a-r,u=f%2!=0,p=0,g=0,v=0,$=0,w=0;w<i&&!(new Date().getTime()>s);w++){for(var y=-w+p;y<=w-g;y+=2){var S=o+y,k;y==-w||y!=w&&l[S-1]<l[S+1]?k=l[S+1]:k=l[S-1]+1;for(var A=k-y;k<a&&A<r&&e.charAt(k)==t.charAt(A);)k++,A++;if(l[S]=k,k>a)g+=2;else if(A>r)p+=2;else if(u){var R=o+f-y;if(R>=0&&R<n&&c[R]!=-1){var C=a-c[R];if(k>=C)return this.diff_bisectSplit_(e,t,k,A,s)}}}for(var M=-w+v;M<=w-$;M+=2){var R=o+M,C;M==-w||M!=w&&c[R-1]<c[R+1]?C=c[R+1]:C=c[R-1]+1;for(var L=C-M;C<a&&L<r&&e.charAt(a-C-1)==t.charAt(r-L-1);)C++,L++;if(c[R]=C,C>a)$+=2;else if(L>r)v+=2;else if(!u){var S=o+f-M;if(S>=0&&S<n&&l[S]!=-1){var k=l[S],A=o+k-S;if(C=a-C,k>=C)return this.diff_bisectSplit_(e,t,k,A,s)}}}}return[new h.Diff(b,e),new h.Diff(_,t)]};h.prototype.diff_bisectSplit_=function(e,t,s,a,r){var i=e.substring(0,s),o=t.substring(0,a),n=e.substring(s),l=t.substring(a),c=this.diff_main(i,o,!1,r),d=this.diff_main(n,l,!1,r);return c.concat(d)};h.prototype.diff_linesToChars_=function(e,t){var s=[],a={};s[0]="";function r(l){for(var c="",d=0,f=-1,u=s.length;f<l.length-1;){f=l.indexOf(`
`,d),f==-1&&(f=l.length-1);var p=l.substring(d,f+1);(a.hasOwnProperty?a.hasOwnProperty(p):a[p]!==void 0)?c+=String.fromCharCode(a[p]):(u==i&&(p=l.substring(d),f=l.length),c+=String.fromCharCode(u),a[p]=u,s[u++]=p),d=f+1}return c}var i=4e4,o=r(e);i=65535;var n=r(t);return{chars1:o,chars2:n,lineArray:s}};h.prototype.diff_charsToLines_=function(e,t){for(var s=0;s<e.length;s++){for(var a=e[s][1],r=[],i=0;i<a.length;i++)r[i]=t[a.charCodeAt(i)];e[s][1]=r.join("")}};h.prototype.diff_commonPrefix=function(e,t){if(!e||!t||e.charAt(0)!=t.charAt(0))return 0;for(var s=0,a=Math.min(e.length,t.length),r=a,i=0;s<r;)e.substring(i,r)==t.substring(i,r)?(s=r,i=s):a=r,r=Math.floor((a-s)/2+s);return r};h.prototype.diff_commonSuffix=function(e,t){if(!e||!t||e.charAt(e.length-1)!=t.charAt(t.length-1))return 0;for(var s=0,a=Math.min(e.length,t.length),r=a,i=0;s<r;)e.substring(e.length-r,e.length-i)==t.substring(t.length-r,t.length-i)?(s=r,i=s):a=r,r=Math.floor((a-s)/2+s);return r};h.prototype.diff_commonOverlap_=function(e,t){var s=e.length,a=t.length;if(s==0||a==0)return 0;s>a?e=e.substring(s-a):s<a&&(t=t.substring(0,s));var r=Math.min(s,a);if(e==t)return r;for(var i=0,o=1;;){var n=e.substring(r-o),l=t.indexOf(n);if(l==-1)return i;o+=l,(l==0||e.substring(r-o)==t.substring(0,o))&&(i=o,o++)}};h.prototype.diff_halfMatch_=function(e,t){if(this.Diff_Timeout<=0)return null;var s=e.length>t.length?e:t,a=e.length>t.length?t:e;if(s.length<4||a.length*2<s.length)return null;var r=this;function i(g,v,$){for(var w=g.substring($,$+Math.floor(g.length/4)),y=-1,S="",k,A,R,C;(y=v.indexOf(w,y+1))!=-1;){var M=r.diff_commonPrefix(g.substring($),v.substring(y)),L=r.diff_commonSuffix(g.substring(0,$),v.substring(0,y));S.length<L+M&&(S=v.substring(y-L,y)+v.substring(y,y+M),k=g.substring(0,$-L),A=g.substring($+M),R=v.substring(0,y-L),C=v.substring(y+M))}return S.length*2>=g.length?[k,A,R,C,S]:null}var o=i(s,a,Math.ceil(s.length/4)),n=i(s,a,Math.ceil(s.length/2)),l;if(!o&&!n)return null;n?o?l=o[4].length>n[4].length?o:n:l=n:l=o;var c,d,f,u;e.length>t.length?(c=l[0],d=l[1],f=l[2],u=l[3]):(f=l[0],u=l[1],c=l[2],d=l[3]);var p=l[4];return[c,d,f,u,p]};h.prototype.diff_cleanupSemantic=function(e){for(var t=!1,s=[],a=0,r=null,i=0,o=0,n=0,l=0,c=0;i<e.length;)e[i][0]==m?(s[a++]=i,o=l,n=c,l=0,c=0,r=e[i][1]):(e[i][0]==_?l+=e[i][1].length:c+=e[i][1].length,r&&r.length<=Math.max(o,n)&&r.length<=Math.max(l,c)&&(e.splice(s[a-1],0,new h.Diff(b,r)),e[s[a-1]+1][0]=_,a--,a--,i=a>0?s[a-1]:-1,o=0,n=0,l=0,c=0,r=null,t=!0)),i++;for(t&&this.diff_cleanupMerge(e),this.diff_cleanupSemanticLossless(e),i=1;i<e.length;){if(e[i-1][0]==b&&e[i][0]==_){var d=e[i-1][1],f=e[i][1],u=this.diff_commonOverlap_(d,f),p=this.diff_commonOverlap_(f,d);u>=p?(u>=d.length/2||u>=f.length/2)&&(e.splice(i,0,new h.Diff(m,f.substring(0,u))),e[i-1][1]=d.substring(0,d.length-u),e[i+1][1]=f.substring(u),i++):(p>=d.length/2||p>=f.length/2)&&(e.splice(i,0,new h.Diff(m,d.substring(0,p))),e[i-1][0]=_,e[i-1][1]=f.substring(0,f.length-p),e[i+1][0]=b,e[i+1][1]=d.substring(p),i++),i++}i++}};h.prototype.diff_cleanupSemanticLossless=function(e){function t(p,g){if(!p||!g)return 6;var v=p.charAt(p.length-1),$=g.charAt(0),w=v.match(h.nonAlphaNumericRegex_),y=$.match(h.nonAlphaNumericRegex_),S=w&&v.match(h.whitespaceRegex_),k=y&&$.match(h.whitespaceRegex_),A=S&&v.match(h.linebreakRegex_),R=k&&$.match(h.linebreakRegex_),C=A&&p.match(h.blanklineEndRegex_),M=R&&g.match(h.blanklineStartRegex_);return C||M?5:A||R?4:w&&!S&&k?3:S||k?2:w||y?1:0}for(var s=1;s<e.length-1;){if(e[s-1][0]==m&&e[s+1][0]==m){var a=e[s-1][1],r=e[s][1],i=e[s+1][1],o=this.diff_commonSuffix(a,r);if(o){var n=r.substring(r.length-o);a=a.substring(0,a.length-o),r=n+r.substring(0,r.length-o),i=n+i}for(var l=a,c=r,d=i,f=t(a,r)+t(r,i);r.charAt(0)===i.charAt(0);){a+=r.charAt(0),r=r.substring(1)+i.charAt(0),i=i.substring(1);var u=t(a,r)+t(r,i);u>=f&&(f=u,l=a,c=r,d=i)}e[s-1][1]!=l&&(l?e[s-1][1]=l:(e.splice(s-1,1),s--),e[s][1]=c,d?e[s+1][1]=d:(e.splice(s+1,1),s--))}s++}};h.nonAlphaNumericRegex_=/[^a-zA-Z0-9]/;h.whitespaceRegex_=/\s/;h.linebreakRegex_=/[\r\n]/;h.blanklineEndRegex_=/\n\r?\n$/;h.blanklineStartRegex_=/^\r?\n\r?\n/;h.prototype.diff_cleanupEfficiency=function(e){for(var t=!1,s=[],a=0,r=null,i=0,o=!1,n=!1,l=!1,c=!1;i<e.length;)e[i][0]==m?(e[i][1].length<this.Diff_EditCost&&(l||c)?(s[a++]=i,o=l,n=c,r=e[i][1]):(a=0,r=null),l=c=!1):(e[i][0]==b?c=!0:l=!0,r&&(o&&n&&l&&c||r.length<this.Diff_EditCost/2&&o+n+l+c==3)&&(e.splice(s[a-1],0,new h.Diff(b,r)),e[s[a-1]+1][0]=_,a--,r=null,o&&n?(l=c=!0,a=0):(a--,i=a>0?s[a-1]:-1,l=c=!1),t=!0)),i++;t&&this.diff_cleanupMerge(e)};h.prototype.diff_cleanupMerge=function(e){e.push(new h.Diff(m,""));for(var t=0,s=0,a=0,r="",i="",o;t<e.length;)switch(e[t][0]){case _:a++,i+=e[t][1],t++;break;case b:s++,r+=e[t][1],t++;break;case m:s+a>1?(s!==0&&a!==0&&(o=this.diff_commonPrefix(i,r),o!==0&&(t-s-a>0&&e[t-s-a-1][0]==m?e[t-s-a-1][1]+=i.substring(0,o):(e.splice(0,0,new h.Diff(m,i.substring(0,o))),t++),i=i.substring(o),r=r.substring(o)),o=this.diff_commonSuffix(i,r),o!==0&&(e[t][1]=i.substring(i.length-o)+e[t][1],i=i.substring(0,i.length-o),r=r.substring(0,r.length-o))),t-=s+a,e.splice(t,s+a),r.length&&(e.splice(t,0,new h.Diff(b,r)),t++),i.length&&(e.splice(t,0,new h.Diff(_,i)),t++),t++):t!==0&&e[t-1][0]==m?(e[t-1][1]+=e[t][1],e.splice(t,1)):t++,a=0,s=0,r="",i="";break}e[e.length-1][1]===""&&e.pop();var n=!1;for(t=1;t<e.length-1;)e[t-1][0]==m&&e[t+1][0]==m&&(e[t][1].substring(e[t][1].length-e[t-1][1].length)==e[t-1][1]?(e[t][1]=e[t-1][1]+e[t][1].substring(0,e[t][1].length-e[t-1][1].length),e[t+1][1]=e[t-1][1]+e[t+1][1],e.splice(t-1,1),n=!0):e[t][1].substring(0,e[t+1][1].length)==e[t+1][1]&&(e[t-1][1]+=e[t+1][1],e[t][1]=e[t][1].substring(e[t+1][1].length)+e[t+1][1],e.splice(t+1,1),n=!0)),t++;n&&this.diff_cleanupMerge(e)};h.prototype.diff_xIndex=function(e,t){var s=0,a=0,r=0,i=0,o;for(o=0;o<e.length&&(e[o][0]!==_&&(s+=e[o][1].length),e[o][0]!==b&&(a+=e[o][1].length),!(s>t));o++)r=s,i=a;return e.length!=o&&e[o][0]===b?i:i+(t-r)};h.prototype.diff_prettyHtml=function(e){for(var t=[],s=/&/g,a=/</g,r=/>/g,i=/\n/g,o=0;o<e.length;o++){var n=e[o][0],l=e[o][1],c=l.replace(s,"&amp;").replace(a,"&lt;").replace(r,"&gt;").replace(i,"&para;<br>");switch(n){case _:t[o]='<ins style="background:#e6ffe6;">'+c+"</ins>";break;case b:t[o]='<del style="background:#ffe6e6;">'+c+"</del>";break;case m:t[o]="<span>"+c+"</span>";break}}return t.join("")};h.prototype.diff_text1=function(e){for(var t=[],s=0;s<e.length;s++)e[s][0]!==_&&(t[s]=e[s][1]);return t.join("")};h.prototype.diff_text2=function(e){for(var t=[],s=0;s<e.length;s++)e[s][0]!==b&&(t[s]=e[s][1]);return t.join("")};h.prototype.diff_levenshtein=function(e){for(var t=0,s=0,a=0,r=0;r<e.length;r++){var i=e[r][0],o=e[r][1];switch(i){case _:s+=o.length;break;case b:a+=o.length;break;case m:t+=Math.max(s,a),s=0,a=0;break}}return t+=Math.max(s,a),t};h.prototype.diff_toDelta=function(e){for(var t=[],s=0;s<e.length;s++)switch(e[s][0]){case _:t[s]="+"+encodeURI(e[s][1]);break;case b:t[s]="-"+e[s][1].length;break;case m:t[s]="="+e[s][1].length;break}return t.join("	").replace(/%20/g," ")};h.prototype.diff_fromDelta=function(e,t){for(var s=[],a=0,r=0,i=t.split(/\t/g),o=0;o<i.length;o++){var n=i[o].substring(1);switch(i[o].charAt(0)){case"+":try{s[a++]=new h.Diff(_,decodeURI(n))}catch{throw new Error("Illegal escape in diff_fromDelta: "+n)}break;case"-":case"=":var l=parseInt(n,10);if(isNaN(l)||l<0)throw new Error("Invalid number in diff_fromDelta: "+n);var c=e.substring(r,r+=l);i[o].charAt(0)=="="?s[a++]=new h.Diff(m,c):s[a++]=new h.Diff(b,c);break;default:if(i[o])throw new Error("Invalid diff operation in diff_fromDelta: "+i[o])}}if(r!=e.length)throw new Error("Delta length ("+r+") does not equal source text length ("+e.length+").");return s};h.prototype.match_main=function(e,t,s){if(e==null||t==null||s==null)throw new Error("Null input. (match_main)");return s=Math.max(0,Math.min(s,e.length)),e==t?0:e.length?e.substring(s,s+t.length)==t?s:this.match_bitap_(e,t,s):-1};h.prototype.match_bitap_=function(e,t,s){if(t.length>this.Match_MaxBits)throw new Error("Pattern too long for this browser.");var a=this.match_alphabet_(t),r=this;function i(k,A){var R=k/t.length,C=Math.abs(s-A);return r.Match_Distance?R+C/r.Match_Distance:C?1:R}var o=this.Match_Threshold,n=e.indexOf(t,s);n!=-1&&(o=Math.min(i(0,n),o),n=e.lastIndexOf(t,s+t.length),n!=-1&&(o=Math.min(i(0,n),o)));var l=1<<t.length-1;n=-1;for(var c,d,f=t.length+e.length,u,p=0;p<t.length;p++){for(c=0,d=f;c<d;)i(p,s+d)<=o?c=d:f=d,d=Math.floor((f-c)/2+c);f=d;var g=Math.max(1,s-d+1),v=Math.min(s+d,e.length)+t.length,$=Array(v+2);$[v+1]=(1<<p)-1;for(var w=v;w>=g;w--){var y=a[e.charAt(w-1)];if(p===0?$[w]=($[w+1]<<1|1)&y:$[w]=($[w+1]<<1|1)&y|((u[w+1]|u[w])<<1|1)|u[w+1],$[w]&l){var S=i(p,w-1);if(S<=o)if(o=S,n=w-1,n>s)g=Math.max(1,2*s-n);else break}}if(i(p+1,s)>o)break;u=$}return n};h.prototype.match_alphabet_=function(e){for(var t={},s=0;s<e.length;s++)t[e.charAt(s)]=0;for(var s=0;s<e.length;s++)t[e.charAt(s)]|=1<<e.length-s-1;return t};h.prototype.patch_addContext_=function(e,t){if(t.length!=0){if(e.start2===null)throw Error("patch not initialized");for(var s=t.substring(e.start2,e.start2+e.length1),a=0;t.indexOf(s)!=t.lastIndexOf(s)&&s.length<this.Match_MaxBits-this.Patch_Margin-this.Patch_Margin;)a+=this.Patch_Margin,s=t.substring(e.start2-a,e.start2+e.length1+a);a+=this.Patch_Margin;var r=t.substring(e.start2-a,e.start2);r&&e.diffs.unshift(new h.Diff(m,r));var i=t.substring(e.start2+e.length1,e.start2+e.length1+a);i&&e.diffs.push(new h.Diff(m,i)),e.start1-=r.length,e.start2-=r.length,e.length1+=r.length+i.length,e.length2+=r.length+i.length}};h.prototype.patch_make=function(e,t,s){var a,r;if(typeof e=="string"&&typeof t=="string"&&typeof s>"u")a=e,r=this.diff_main(a,t,!0),r.length>2&&(this.diff_cleanupSemantic(r),this.diff_cleanupEfficiency(r));else if(e&&typeof e=="object"&&typeof t>"u"&&typeof s>"u")r=e,a=this.diff_text1(r);else if(typeof e=="string"&&t&&typeof t=="object"&&typeof s>"u")a=e,r=t;else if(typeof e=="string"&&typeof t=="string"&&s&&typeof s=="object")a=e,r=s;else throw new Error("Unknown call format to patch_make.");if(r.length===0)return[];for(var i=[],o=new h.patch_obj,n=0,l=0,c=0,d=a,f=a,u=0;u<r.length;u++){var p=r[u][0],g=r[u][1];switch(!n&&p!==m&&(o.start1=l,o.start2=c),p){case _:o.diffs[n++]=r[u],o.length2+=g.length,f=f.substring(0,c)+g+f.substring(c);break;case b:o.length1+=g.length,o.diffs[n++]=r[u],f=f.substring(0,c)+f.substring(c+g.length);break;case m:g.length<=2*this.Patch_Margin&&n&&r.length!=u+1?(o.diffs[n++]=r[u],o.length1+=g.length,o.length2+=g.length):g.length>=2*this.Patch_Margin&&n&&(this.patch_addContext_(o,d),i.push(o),o=new h.patch_obj,n=0,d=f,l=c);break}p!==_&&(l+=g.length),p!==b&&(c+=g.length)}return n&&(this.patch_addContext_(o,d),i.push(o)),i};h.prototype.patch_deepCopy=function(e){for(var t=[],s=0;s<e.length;s++){var a=e[s],r=new h.patch_obj;r.diffs=[];for(var i=0;i<a.diffs.length;i++)r.diffs[i]=new h.Diff(a.diffs[i][0],a.diffs[i][1]);r.start1=a.start1,r.start2=a.start2,r.length1=a.length1,r.length2=a.length2,t[s]=r}return t};h.prototype.patch_apply=function(e,t){if(e.length==0)return[t,[]];e=this.patch_deepCopy(e);var s=this.patch_addPadding(e);t=s+t+s,this.patch_splitMax(e);for(var a=0,r=[],i=0;i<e.length;i++){var o=e[i].start2+a,n=this.diff_text1(e[i].diffs),l,c=-1;if(n.length>this.Match_MaxBits?(l=this.match_main(t,n.substring(0,this.Match_MaxBits),o),l!=-1&&(c=this.match_main(t,n.substring(n.length-this.Match_MaxBits),o+n.length-this.Match_MaxBits),(c==-1||l>=c)&&(l=-1))):l=this.match_main(t,n,o),l==-1)r[i]=!1,a-=e[i].length2-e[i].length1;else{r[i]=!0,a=l-o;var d;if(c==-1?d=t.substring(l,l+n.length):d=t.substring(l,c+this.Match_MaxBits),n==d)t=t.substring(0,l)+this.diff_text2(e[i].diffs)+t.substring(l+n.length);else{var f=this.diff_main(n,d,!1);if(n.length>this.Match_MaxBits&&this.diff_levenshtein(f)/n.length>this.Patch_DeleteThreshold)r[i]=!1;else{this.diff_cleanupSemanticLossless(f);for(var u=0,p,g=0;g<e[i].diffs.length;g++){var v=e[i].diffs[g];v[0]!==m&&(p=this.diff_xIndex(f,u)),v[0]===_?t=t.substring(0,l+p)+v[1]+t.substring(l+p):v[0]===b&&(t=t.substring(0,l+p)+t.substring(l+this.diff_xIndex(f,u+v[1].length))),v[0]!==b&&(u+=v[1].length)}}}}}return t=t.substring(s.length,t.length-s.length),[t,r]};h.prototype.patch_addPadding=function(e){for(var t=this.Patch_Margin,s="",a=1;a<=t;a++)s+=String.fromCharCode(a);for(var a=0;a<e.length;a++)e[a].start1+=t,e[a].start2+=t;var r=e[0],i=r.diffs;if(i.length==0||i[0][0]!=m)i.unshift(new h.Diff(m,s)),r.start1-=t,r.start2-=t,r.length1+=t,r.length2+=t;else if(t>i[0][1].length){var o=t-i[0][1].length;i[0][1]=s.substring(i[0][1].length)+i[0][1],r.start1-=o,r.start2-=o,r.length1+=o,r.length2+=o}if(r=e[e.length-1],i=r.diffs,i.length==0||i[i.length-1][0]!=m)i.push(new h.Diff(m,s)),r.length1+=t,r.length2+=t;else if(t>i[i.length-1][1].length){var o=t-i[i.length-1][1].length;i[i.length-1][1]+=s.substring(0,o),r.length1+=o,r.length2+=o}return s};h.prototype.patch_splitMax=function(e){for(var t=this.Match_MaxBits,s=0;s<e.length;s++)if(!(e[s].length1<=t)){var a=e[s];e.splice(s--,1);for(var r=a.start1,i=a.start2,o="";a.diffs.length!==0;){var n=new h.patch_obj,l=!0;for(n.start1=r-o.length,n.start2=i-o.length,o!==""&&(n.length1=n.length2=o.length,n.diffs.push(new h.Diff(m,o)));a.diffs.length!==0&&n.length1<t-this.Patch_Margin;){var c=a.diffs[0][0],d=a.diffs[0][1];c===_?(n.length2+=d.length,i+=d.length,n.diffs.push(a.diffs.shift()),l=!1):c===b&&n.diffs.length==1&&n.diffs[0][0]==m&&d.length>2*t?(n.length1+=d.length,r+=d.length,l=!1,n.diffs.push(new h.Diff(c,d)),a.diffs.shift()):(d=d.substring(0,t-n.length1-this.Patch_Margin),n.length1+=d.length,r+=d.length,c===m?(n.length2+=d.length,i+=d.length):l=!1,n.diffs.push(new h.Diff(c,d)),d==a.diffs[0][1]?a.diffs.shift():a.diffs[0][1]=a.diffs[0][1].substring(d.length))}o=this.diff_text2(n.diffs),o=o.substring(o.length-this.Patch_Margin);var f=this.diff_text1(a.diffs).substring(0,this.Patch_Margin);f!==""&&(n.length1+=f.length,n.length2+=f.length,n.diffs.length!==0&&n.diffs[n.diffs.length-1][0]===m?n.diffs[n.diffs.length-1][1]+=f:n.diffs.push(new h.Diff(m,f))),l||e.splice(++s,0,n)}}};h.prototype.patch_toText=function(e){for(var t=[],s=0;s<e.length;s++)t[s]=e[s];return t.join("")};h.prototype.patch_fromText=function(e){var t=[];if(!e)return t;for(var s=e.split(`
`),a=0,r=/^@@ -(\d+),?(\d*) \+(\d+),?(\d*) @@$/;a<s.length;){var i=s[a].match(r);if(!i)throw new Error("Invalid patch string: "+s[a]);var o=new h.patch_obj;for(t.push(o),o.start1=parseInt(i[1],10),i[2]===""?(o.start1--,o.length1=1):i[2]=="0"?o.length1=0:(o.start1--,o.length1=parseInt(i[2],10)),o.start2=parseInt(i[3],10),i[4]===""?(o.start2--,o.length2=1):i[4]=="0"?o.length2=0:(o.start2--,o.length2=parseInt(i[4],10)),a++;a<s.length;){var n=s[a].charAt(0);try{var l=decodeURI(s[a].substring(1))}catch{throw new Error("Illegal escape in patch_fromText: "+l)}if(n=="-")o.diffs.push(new h.Diff(b,l));else if(n=="+")o.diffs.push(new h.Diff(_,l));else if(n==" ")o.diffs.push(new h.Diff(m,l));else{if(n=="@")break;if(n!=="")throw new Error('Invalid patch mode "'+n+'" in: '+l)}a++}}return t};h.patch_obj=function(){this.diffs=[],this.start1=null,this.start2=null,this.length1=0,this.length2=0};h.patch_obj.prototype.toString=function(){var e,t;this.length1===0?e=this.start1+",0":this.length1==1?e=this.start1+1:e=this.start1+1+","+this.length1,this.length2===0?t=this.start2+",0":this.length2==1?t=this.start2+1:t=this.start2+1+","+this.length2;for(var s=["@@ -"+e+" +"+t+` @@
`],a,r=0;r<this.diffs.length;r++){switch(this.diffs[r][0]){case _:a="+";break;case b:a="-";break;case m:a=" ";break}s[r+1]=a+encodeURI(this.diffs[r][1])+`
`}return s.join("").replace(/%20/g," ")};D.exports=h;D.exports.diff_match_patch=h;D.exports.DIFF_DELETE=b;D.exports.DIFF_INSERT=_;D.exports.DIFF_EQUAL=m});var{ApiService:T}=Shopware.Classes,B=class extends T{constructor(t,s,a="_action/frosh-tools"){super(t,s,a)}getCacheInfo(){let t=`${this.getApiBasePath()}/cache`;return this.httpClient.get(t,{headers:this.getBasicHeaders()}).then(s=>T.handleResponse(s))}clearCache(t){let s=`${this.getApiBasePath()}/cache/${t}`;return this.httpClient.delete(s,{headers:this.getBasicHeaders()}).then(a=>T.handleResponse(a))}clearOPcache(){let t=`${this.getApiBasePath()}/cache_clear_opcache`;return this.httpClient.delete(t,{headers:this.getBasicHeaders()}).then(s=>T.handleResponse(s))}getQueue(){let t=`${this.getApiBasePath()}/queue/list`;return this.httpClient.get(t,{headers:this.getBasicHeaders()}).then(s=>T.handleResponse(s))}resetQueue(){let t=`${this.getApiBasePath()}/queue`;return this.httpClient.delete(t,{headers:this.getBasicHeaders()}).then(s=>T.handleResponse(s))}runScheduledTask(t){let s=`${this.getApiBasePath()}/scheduled-task/${t}`;return this.httpClient.post(s,{},{headers:this.getBasicHeaders()}).then(a=>T.handleResponse(a))}scheduleScheduledTask(t,s=!1){let a=`${this.getApiBasePath()}/scheduled-task/schedule/${t}`;return this.httpClient.post(a,{immediately:s},{headers:this.getBasicHeaders()}).then(r=>T.handleResponse(r))}deactivateScheduledTask(t){let s=`${this.getApiBasePath()}/scheduled-task/deactivate/${t}`;return this.httpClient.post(s,{},{headers:this.getBasicHeaders()}).then(a=>T.handleResponse(a))}scheduledTasksRegister(){let t=`${this.getApiBasePath()}/scheduled-tasks/register`;return this.httpClient.post(t,{},{headers:this.getBasicHeaders()}).then(s=>T.handleResponse(s))}healthStatus(t=!1){if(!this.loginService.isLoggedIn())return;let s=`${this.getApiBasePath()}/health/status`;return t&&(s=`${this.getApiBasePath()}/health-ping/status`),this.httpClient.get(s,{headers:this.getBasicHeaders()}).then(a=>T.handleResponse(a))}performanceStatus(){let t=`${this.getApiBasePath()}/performance/status`;return this.httpClient.get(t,{headers:this.getBasicHeaders()}).then(s=>T.handleResponse(s))}getLogFiles(){let t=`${this.getApiBasePath()}/logs/files`;return this.httpClient.get(t,{headers:this.getBasicHeaders()}).then(s=>T.handleResponse(s))}getLogFile(t,s=0,a=20){let r=`${this.getApiBasePath()}/logs/file`;return this.httpClient.get(r,{params:{file:t,offset:s,limit:a},headers:this.getBasicHeaders()}).then(i=>i)}getShopwareFiles(){let t=`${this.getApiBasePath()}/shopware-files`;return this.httpClient.get(t,{headers:this.getBasicHeaders()}).then(s=>s)}getExtensionFiles(){let t=`${this.getApiBasePath()}/extension-files`;return this.httpClient.get(t,{headers:this.getBasicHeaders()}).then(s=>s)}getFileContents(t){let s=`${this.getApiBasePath()}/file-contents`;return this.httpClient.get(s,{params:{file:t},headers:this.getBasicHeaders()}).then(a=>a)}restoreShopwareFile(t){let s=`${this.getApiBasePath()}/shopware-file/restore`;return this.httpClient.get(s,{params:{file:t},headers:this.getBasicHeaders()}).then(a=>a)}getFeatureFlags(){let t=`${this.getApiBasePath()}/feature-flag/list`;return this.httpClient.get(t,{headers:this.getBasicHeaders()}).then(s=>T.handleResponse(s))}stateMachines(t){let s=`${this.getApiBasePath()}/state-machines/load/${t}`;return this.httpClient.get(s,{headers:this.getBasicHeaders()}).then(a=>T.handleResponse(a))}getFastlyStatus(){let t=`${this.getApiBasePath()}/fastly/status`;return this.httpClient.get(t,{headers:this.getBasicHeaders()}).then(s=>T.handleResponse(s))}fastlyPurge(t){let s=`${this.getApiBasePath()}/fastly/purge`;return this.httpClient.post(s,{path:t},{headers:this.getBasicHeaders()}).then(a=>T.handleResponse(a))}fastlyPurgeAll(){let t=`${this.getApiBasePath()}/fastly/purge-all`;return this.httpClient.post(t,{},{headers:this.getBasicHeaders()}).then(s=>T.handleResponse(s))}getFastlyStatistics(t){let s=`${this.getApiBasePath()}/fastly/statistics`;return this.httpClient.get(s,{headers:this.getBasicHeaders(),params:{timeframe:t}}).then(a=>T.handleResponse(a))}getFastlySnippets(){let t=`${this.getApiBasePath()}/fastly/snippets`;return this.httpClient.get(t,{headers:this.getBasicHeaders()}).then(s=>T.handleResponse(s))}},N=B;var{ApiService:E}=Shopware.Classes,F=class extends E{constructor(t,s,a="_action/frosh-tools/elasticsearch"){super(t,s,a)}status(){let t=`${this.getApiBasePath()}/status`;return this.httpClient.get(t,{headers:this.getBasicHeaders()}).then(s=>E.handleResponse(s))}indices(){let t=`${this.getApiBasePath()}/indices`;return this.httpClient.get(t,{headers:this.getBasicHeaders()}).then(s=>E.handleResponse(s))}deleteIndex(t){let s=`${this.getApiBasePath()}/index/`+t;return this.httpClient.delete(s,{headers:this.getBasicHeaders()}).then(a=>E.handleResponse(a))}console(t,s,a){let r=`${this.getApiBasePath()}/console`+s;return this.httpClient.request({url:r,method:t,headers:{...this.getBasicHeaders(),"content-type":"application/json"},data:a}).then(i=>E.handleResponse(i))}flushAll(){let t=`${this.getApiBasePath()}/flush_all`;return this.httpClient.post(t,{},{headers:this.getBasicHeaders()}).then(s=>E.handleResponse(s))}reindex(){let t=`${this.getApiBasePath()}/reindex`;return this.httpClient.post(t,{},{headers:this.getBasicHeaders()}).then(s=>E.handleResponse(s))}switchAlias(){let t=`${this.getApiBasePath()}/switch_alias`;return this.httpClient.post(t,{},{headers:this.getBasicHeaders()}).then(s=>E.handleResponse(s))}cleanup(){let t=`${this.getApiBasePath()}/cleanup`;return this.httpClient.post(t,{},{headers:this.getBasicHeaders()}).then(s=>E.handleResponse(s))}reset(){let t=`${this.getApiBasePath()}/reset`;return this.httpClient.post(t,{},{headers:this.getBasicHeaders()}).then(s=>E.handleResponse(s))}},x=F;var{Application:P}=Shopware;P.addServiceProvider("froshToolsService",e=>{let t=P.getContainer("init");return new N(t.httpClient,e.loginService)});P.addServiceProvider("froshElasticSearch",e=>{let t=P.getContainer("init");return new x(t.httpClient,e.loginService)});var H=`{% block sw_data_grid_inline_edit_type_unknown %}
<sw-datepicker
    deprecated
    v-else-if="column.inlineEdit === 'date'"
    key="date"
    date-type="date"
    v-model:value="currentValue"
    name="sw-field--currentValue"
/>

<sw-datepicker
    deprecated
    v-else-if="column.inlineEdit === 'datetime'"
    key="datetime"
    date-type="datetime"
    v-model:value="currentValue"
    name="sw-field--currentValue"
/>

{% parent() %}
{% endblock %}`;var{Component:ce}=Shopware;ce.override("sw-data-grid-inline-edit",{template:H});var O=`{% block sw_version_status %}
<router-link
    v-if="hasPermission"
    :to="{ name: 'frosh.tools.index.index' }"
    class="sw-version__status has-permission"
    v-tooltip="{
            showDelay: 300,
            message: healthPlaceholder
        }"
>
    {% block sw_version_status_badge %}
    <sw-color-badge
        v-if="health && hasPermission"
        :variant="healthVariant"
        :rounded="true"
    ></sw-color-badge>

    <template v-else>
        {% parent() %}
    </template>
    {% endblock %}
</router-link>

<template v-else>
    {% parent() %}
</template>
{% endblock %}`;var{Component:fe}=Shopware;fe.override("sw-version",{template:O,inject:["froshToolsService","acl","loginService"],async created(){this.checkPermission()&&await this.checkHealth()},data(){return{health:null,hasPermission:!1}},computed:{healthVariant(){let e="success";for(let t of this.health){if(t.state==="STATE_ERROR"){e="error";continue}t.state==="STATE_WARNING"&&e==="success"&&(e="warning")}return e},healthPlaceholder(){let e="Shop Status: Ok";if(this.health===null)return e;for(let t of this.health){if(t.state==="STATE_ERROR"){e="Shop Status: May outage, Check System Status";continue}t.state==="STATE_WARNING"&&e==="Shop Status: Ok"&&(e="Shop Status: Issues, Check System Status")}return e}},methods:{async checkHealth(){this.health=await this.froshToolsService.healthStatus(!0),this.checkInterval=setInterval(async()=>{try{this.health=await this.froshToolsService.healthStatus(!0)}catch(e){console.error(e),clearInterval(this.checkInterval)}},6e4),this.loginService.addOnLogoutListener(()=>clearInterval(this.checkInterval))},checkPermission(){return this.hasPermission=this.acl.can("frosh_tools:read")}}});var z=`<sw-card-view>
    <sw-card
        class="frosh-tools-tab-index__health-card"
        :isLoading="isLoading"
        :large="true"
        positionIdentifier="frosh-tools-tab-index"
        deprecated
    >
        <template #toolbar>
            <!-- @todo: Make the refresh button fancy -->
            <sw-button
                variant="ghost"
                deprecated
                @click="refresh"
            >
                <sw-icon
                    deprecated
                    :small="true"
                    name="regular-undo"
                ></sw-icon>
            </sw-button>
        </template>
        <sw-card
            class="frosh-tools-tab-index__health-card"
            :title="$t('frosh-tools.tabs.index.title')"
            deprecated
            :large="true"
            positionIdentifier="frosh-tools-tab-index-health"
        >
            <template #grid>
                <sw-data-grid
                    v-if="health"
                    :showSelection="false"
                    :showActions="false"
                    :dataSource="health"
                    :columns="columns"
                >
                    <template #column-current="{ item }">
                        <div>
                            <sw-label
                                variant="warning"
                                appearance="pill"
                                v-if="item.state === 'STATE_WARNING'"
                            >
                                {{ $t('frosh-tools.warning') }}
                            </sw-label>
                            <sw-label
                                variant="danger"
                                appearance="pill"
                                v-if="item.state === 'STATE_ERROR'"
                            >
                                {{ $t('frosh-tools.error') }}
                            </sw-label>
                            <sw-label
                                variant="info"
                                appearance="pill"
                                v-if="item.state === 'STATE_INFO'"
                            >
                                {{ $t('frosh-tools.info') }}
                            </sw-label>
                        </div>
                        {{ item.current }}
                    </template>

                    <template #column-name="{ item }">
                        {{ item.snippet }}
                        <template v-if="item.url">
                            &nbsp;
                            <a
                                :href="item.url"
                                target="_blank"
                            >Info</a>
                        </template>
                    </template>
                </sw-data-grid>
            </template>
        </sw-card>
        <sw-card
            class="frosh-tools-tab-index__performance-card"
            :title="$t('frosh-tools.tabs.index.performance')"
            :large="true"
            positionIdentifier="frosh-tools-tab-index-performance"
            deprecated
        >
            <template #grid>
                <sw-card-section
                    divider="bottom"
                    v-if="performanceStatus && performanceStatus.length === 0"
                >
                    <sw-alert
                        variant="success"
                        deprecated
                    >
                        {{ $t('frosh-tools.noRecommendations') }}
                    </sw-alert>
                </sw-card-section>
                <sw-data-grid
                    v-if="performanceStatus && performanceStatus.length > 0"
                    :showSelection="false"
                    :showActions="false"
                    :dataSource="performanceStatus"
                    :columns="columns"
                >
                    <template #column-current="{ item }">
                        <div>
                            <sw-label
                                variant="warning"
                                appearance="pill"
                                v-if="item.state === 'STATE_WARNING'"
                            >
                                {{ $t('frosh-tools.warning') }}
                            </sw-label>
                            <sw-label
                                variant="danger"
                                appearance="pill"
                                v-if="item.state === 'STATE_ERROR'"
                            >
                                {{ $t('frosh-tools.error') }}
                            </sw-label>
                            <sw-label
                                variant="info"
                                appearance="pill"
                                v-if="item.state === 'STATE_INFO'"
                            >
                                {{ $t('frosh-tools.info') }}
                            </sw-label>
                            {{ item.current }}
                        </div>
                    </template>

                    <template #column-name="{ item }">
                        {{ item.snippet }}
                        <template v-if="item.url">
                            &nbsp;
                            <a
                                :href="item.url"
                                target="_blank"
                            >
                                {{ $t('frosh-tools.tabs.index.info') }}
                            </a>
                        </template>
                    </template>
                </sw-data-grid>
            </template>
        </sw-card>
    </sw-card>
</sw-card-view>`;var{Component:pe}=Shopware;pe.register("frosh-tools-tab-index",{inject:["froshToolsService"],template:z,data(){return{isLoading:!0,health:null,performanceStatus:null}},created(){this.createdComponent()},computed:{columns(){return[{property:"name",label:"frosh-tools.name",rawData:!0},{property:"current",label:"frosh-tools.current",rawData:!0},{property:"recommended",label:"frosh-tools.recommended",rawData:!0}]}},methods:{async refresh(){this.isLoading=!0,await this.createdComponent()},async createdComponent(){this.health=await this.froshToolsService.healthStatus(),this.performanceStatus=await this.froshToolsService.performanceStatus(),this.isLoading=!1}}});var j=`<sw-card-view>
    <sw-card
        class="frosh-tools-tab-cache__cache-card"
        deprecated
        :title="$t('frosh-tools.tabs.cache.title')"
        :isLoading="isLoading"
        :large="true"
        positionIdentifier="frosh-tools-tab-cache"
    >
        <template #toolbar>
            <!-- @todo: Make the refresh button fancy -->
            <sw-button
                variant="ghost"
                deprecated
                @click="createdComponent"
            >
                <sw-icon
                    deprecated
                    :small="true"
                    name="regular-undo"
                ></sw-icon>
            </sw-button>
        </template>

        <template #grid>
            <sw-data-grid
                :showSelection="false"
                :dataSource="cacheFolders"
                :columns="columns"
            >
                <template #column-name="{ item }">
                    <sw-label
                        variant="success"
                        appearance="pill"
                        v-if="item.active"
                    >
                        {{ $t('frosh-tools.active') }}
                    </sw-label>
                    <sw-label
                        variant="primary"
                        appearance="pill"
                        v-if="item.type"
                    >
                        {{ item.type }}
                    </sw-label>
                    {{ item.name }}
                </template>

                <template #column-size="{ item }">
                    <template v-if="item.size < 0">
                        {{ $t('frosh-tools.unknown') }}
                    </template>

                    <template v-else>
                        {{ formatSize(item.size) }}
                    </template>
                </template>

                <template #column-freeSpace="{ item }">
                    <template v-if="item.freeSpace < 0">
                        {{ $t('frosh-tools.unknown') }}
                    </template>

                    <template v-else>
                        {{ formatSize(item.freeSpace) }}
                    </template>
                </template>

                <template #actions="{ item }">
                    <sw-context-menu-item
                        variant="danger"
                        @click="clearCache(item)"
                    >
                        {{ $t('frosh-tools.clear') }}
                    </sw-context-menu-item>
                </template>
            </sw-data-grid>
        </template>
    </sw-card>
    <sw-card
        class="frosh-tools-tab-cache__action-card"
        deprecated
        :title="$t('frosh-tools.actions')"
        :isLoading="isLoading"
        :large="true"
        positionIdentifier="frosh-tools-tab-cache-action"
    >
        <sw-button
            variant="primary"
            deprecated
            @click="compileTheme"
        >
            {{ $t('frosh-tools.compileTheme') }}
        </sw-button>
        <sw-button
            variant="primary"
            deprecated
            @click="clearOPcache"
        >
            {{ $t('frosh-tools.clearOpCache') }}
        </sw-button>
    </sw-card>
</sw-card-view>`;var{Component:ge,Mixin:we}=Shopware,{Criteria:ve}=Shopware.Data;ge.register("frosh-tools-tab-cache",{template:j,inject:["froshToolsService","repositoryFactory","themeService"],mixins:[we.getByName("notification")],data(){return{cacheInfo:null,isLoading:!0,numberFormater:null}},created(){let e=Shopware.Application.getContainer("factory").locale.getLastKnownLocale();this.numberFormater=new Intl.NumberFormat(e,{minimumFractionDigits:2,maximumFractionDigits:2}),this.createdComponent()},computed:{columns(){return[{property:"name",label:"frosh-tools.name",rawData:!0},{property:"size",label:"frosh-tools.used",rawData:!0,align:"right"},{property:"freeSpace",label:"frosh-tools.free",rawData:!0,align:"right"}]},cacheFolders(){return this.cacheInfo===null?[]:this.cacheInfo},salesChannelRepository(){return this.repositoryFactory.create("sales_channel")}},methods:{async createdComponent(){this.isLoading=!0,this.cacheInfo=await this.froshToolsService.getCacheInfo(),this.isLoading=!1},formatSize(e){let t=e/1048576;return this.numberFormater.format(t)+" MiB"},async clearCache(e){this.isLoading=!0,await this.froshToolsService.clearCache(e.name),await this.createdComponent()},async compileTheme(){let e=new ve;e.addAssociation("themes"),this.isLoading=!0;let t=await this.salesChannelRepository.search(e,Shopware.Context.api);for(let s of t){let a=s.extensions.themes.first();a&&(await this.themeService.assignTheme(a.id,s.id),this.createNotificationSuccess({message:`${s.translated.name}: ${this.$t("frosh-tools.themeCompiled")}`}))}this.isLoading=!1},async clearOPcache(){this.isLoading=!0,await this.froshToolsService.clearOPcache(),this.createNotificationSuccess({message:this.$t("frosh-tools.clearedOpcache")}),await this.createdComponent()}}});var q=`<sw-card-view>
    <sw-card
        class="frosh-tools-tab-queue__manager-card"
        :title="$t('frosh-tools.tabs.queue.title')"
        :isLoading="isLoading"
        :large="true"
        positionIdentifier="frosh-tools-tab-queue"
        deprecated
    >
        <template #toolbar>
            <sw-button
                variant="ghost"
                deprecated
                @click="refresh"
            >
                <sw-icon
                    :small="true"
                    deprecated
                    name="regular-undo"
                ></sw-icon>
            </sw-button>
            <sw-button
                deprecated
                @click="showResetModal = true"
            >
                {{ $t('frosh-tools.resetQueue') }}
            </sw-button>
        </template>

        <template #grid>
            <sw-data-grid
                :showSelection="false"
                :dataSource="queueEntries"
                :columns="columns"
                :showActions="false"
            >
            </sw-data-grid>
        </template>
    </sw-card>
    <sw-modal
        v-if="showResetModal"
        :title="$t('frosh-tools.tabs.queue.reset.modal.title')"
        variant="small"
        @modal-close="showResetModal = false"
    >
        {{ $t('frosh-tools.tabs.queue.reset.modal.description') }}
        <template #modal-footer>
            <sw-button
                deprecated
                @click="showResetModal = false"
            >
                {{ $t('global.default.cancel') }}
            </sw-button>
            <sw-button
                deprecated
                @click="resetQueue"
                variant="primary"
            >
                {{ $t('frosh-tools.tabs.queue.reset.modal.reset') }}
            </sw-button>
        </template>
    </sw-modal>
</sw-card-view>`;var{Component:_e,Mixin:ye}=Shopware;_e.register("frosh-tools-tab-queue",{template:q,inject:["repositoryFactory","froshToolsService"],mixins:[ye.getByName("notification")],data(){return{queueEntries:[],showResetModal:!1,isLoading:!0}},created(){this.createdComponent()},computed:{columns(){return[{property:"name",label:"frosh-tools.name",rawData:!0},{property:"size",label:"frosh-tools.size",rawData:!0}]}},methods:{async refresh(){this.isLoading=!0,await this.createdComponent()},async createdComponent(){this.queueEntries=await this.froshToolsService.getQueue();for(let e of this.queueEntries){let t=e.name.split("\\");e.name=t[t.length-1]}this.isLoading=!1},async resetQueue(){this.isLoading=!0,await this.froshToolsService.resetQueue(),this.showResetModal=!1,await this.createdComponent(),this.createNotificationSuccess({message:this.$t("frosh-tools.tabs.queue.reset.success")}),this.isLoading=!1}}});var U=`<sw-card-view>
    <sw-card
        class="frosh-tools-tab-scheduled__tasks-card"
        :title="$t('frosh-tools.tabs.scheduledTaskOverview.title')"
        :isLoading="isLoading"
        :large="true"
        positionIdentifier="frosh-tools-tab-scheduled"
        deprecated
    >
        <template #toolbar>
            <sw-button
                deprecated
                variant="ghost"
                @click="refresh"
            >
                <sw-icon
                    :small="true"
                    name="regular-undo"
                    deprecated
                ></sw-icon>
            </sw-button>
            <sw-button
                deprecated
                variant="primary"
                @click="registerScheduledTasks"
            >
                {{ $t('frosh-tools.scheduledTasksRegisterStarted') }}
            </sw-button>
        </template>

        <template #grid>
            <sw-entity-listing
                :showSelection="false"
                :fullPage="false"
                :allowInlineEdit="true"
                :allowEdit="false"
                :allowDelete="false"
                :showActions="true"
                :repository="scheduledRepository"
                :items="items"
                :columns="columns"
            >
                <template #column-lastExecutionTime="{ item }">
                    {{ date(item.lastExecutionTime, {hour: '2-digit', minute: '2-digit'}) }}
                </template>

                <template
                    #column-nextExecutionTime="{ item, column, compact, isInlineEdit }"
                >
                    <sw-data-grid-inline-edit
                        v-if="isInlineEdit"
                        :column="column"
                        :compact="compact"
                        :value="item[column.property]"
                        @update:value="item[column.property] = $event"
                    >
                    </sw-data-grid-inline-edit>
                    <span v-else>
                        {{ date(item.nextExecutionTime, {hour: '2-digit', minute: '2-digit'}) }}
                    </span>
                </template>

                <template #actions="{ item }">
                    <sw-context-menu-item
                        variant="primary"
                        @click="runTask(item)"
                    >
                        {{ $t('frosh-tools.runManually') }}
                    </sw-context-menu-item>
                    <sw-context-menu-item
                        variant="primary"
                        @click="scheduleTask(item)"
                    >
                        {{ $t('frosh-tools.setToScheduled') }}
                    </sw-context-menu-item>
                    <sw-context-menu-item
                        variant="primary"
                        @click="scheduleTask(item, true)"
                    >
                        {{ $t('frosh-tools.setToScheduledImmediately') }}
                    </sw-context-menu-item>
                    <sw-context-menu-item
                        variant="primary"
                        @click="deactivateTask(item)"
                    >
                        {{ $t('frosh-tools.setToInactive') }}
                    </sw-context-menu-item>
                </template>
            </sw-entity-listing>
        </template>
    </sw-card>
    <sw-modal
        v-if="taskError"
        :title="$t('global.default.error')"
        @modal-close="taskError = null"
    >
        <pre
            v-if="typeof taskError === 'object'"
            v-text="taskError"
        />
        <div
            v-else
            v-html="taskError"
        />
        <template #modal-footer>
            <sw-button
                deprecated
                size="small"
                @click="taskError = null"
            >
                {{ $t('global.default.close') }}
            </sw-button>
        </template>
    </sw-modal>
</sw-card-view>`;var{Component:ke,Mixin:$e}=Shopware,{Criteria:Q}=Shopware.Data;ke.register("frosh-tools-tab-scheduled",{template:U,inject:["repositoryFactory","froshToolsService"],mixins:[$e.getByName("notification")],data(){return{items:null,showResetModal:!1,isLoading:!0,page:1,limit:25,taskError:null}},created(){this.createdComponent()},computed:{scheduledRepository(){return this.repositoryFactory.create("scheduled_task")},columns(){return[{property:"name",label:"frosh-tools.name",rawData:!0,primary:!0},{property:"runInterval",label:"frosh-tools.interval",rawData:!0,inlineEdit:"number"},{property:"lastExecutionTime",label:"frosh-tools.lastExecutionTime",rawData:!0},{property:"nextExecutionTime",label:"frosh-tools.nextExecutionTime",rawData:!0,inlineEdit:"datetime"},{property:"status",label:"frosh-tools.status",rawData:!0}]},date(){return Shopware.Filter.getByName("date")}},methods:{async refresh(){this.isLoading=!0,await this.createdComponent()},async createdComponent(){let e=new Q(this.page,this.limit);e.addSorting(Q.sort("nextExecutionTime","ASC")),this.items=await this.scheduledRepository.search(e,Shopware.Context.api),this.isLoading=!1},async runTask(e){this.isLoading=!0;try{this.createNotificationInfo({message:this.$t("frosh-tools.scheduledTaskStarted",{name:e.name})}),await this.froshToolsService.runScheduledTask(e.id),this.createNotificationSuccess({message:this.$t("frosh-tools.scheduledTaskSucceed",{name:e.name})})}catch(t){this.createNotificationError({message:this.$t("frosh-tools.scheduledTaskFailed",{name:e.name})}),this.taskError=t.response.data}this.createdComponent()},async scheduleTask(e,t=!1){this.isLoading=!0;try{this.createNotificationInfo({message:this.$t("frosh-tools.scheduledTaskScheduleStarted",{name:e.name})}),await this.froshToolsService.scheduleScheduledTask(e.id,t),this.createNotificationSuccess({message:this.$t("frosh-tools.scheduledTaskScheduleSucceed",{name:e.name})})}catch(s){this.createNotificationError({message:this.$t("frosh-tools.scheduledTaskScheduleFailed",{name:e.name})}),this.taskError=s.response.data}this.createdComponent()},async deactivateTask(e){this.isLoading=!0;try{this.createNotificationInfo({message:this.$t("frosh-tools.scheduledTaskDeactivateStarted",{name:e.name})}),await this.froshToolsService.deactivateScheduledTask(e.id),this.createNotificationSuccess({message:this.$t("frosh-tools.scheduledTaskDeactivateSucceed",{name:e.name})})}catch(t){this.createNotificationError({message:this.$t("frosh-tools.scheduledTaskDeactivateFailed",{name:e.name})}),this.taskError=t.response.data}this.createdComponent()},async registerScheduledTasks(){this.isLoading=!0;try{this.createNotificationInfo({message:this.$t("frosh-tools.scheduledTasksRegisterStarted")}),await this.froshToolsService.scheduledTasksRegister(),this.createNotificationSuccess({message:this.$t("frosh-tools.scheduledTasksRegisterSucceed")})}catch(e){this.createNotificationError({message:this.$t("frosh-tools.scheduledTasksRegisterFailed")}),this.taskError=e.response.data}this.createdComponent()}}});var V=`<sw-card-view>
    <sw-card
        :title="$t('frosh-tools.tabs.elasticsearch.title')"
        deprecated
        :large="true"
        :isLoading="isLoading"
        positionIdentifier="frosh-tools-tab-elasticsearch"
    >
        <sw-alert
            variant="error"
            deprecated
            v-if="!isLoading && !isActive"
        >
            {{ $t('frosh-tools.tabs.elasticsearch.disabled') }}
        </sw-alert>
        <div v-if="!isLoading && isActive">
            <div>
                <strong>
                    {{ $t('frosh-tools.tabs.elasticsearch.version') }}
                    :
                </strong>
                {{ statusInfo.info.version.number }}
            </div>
            <div>
                <strong>
                    {{ $t('frosh-tools.tabs.elasticsearch.nodes') }}
                    :
                </strong>
                {{ statusInfo.health.number_of_nodes }}
            </div>
            <div>
                <strong>
                    {{ $t('frosh-tools.tabs.elasticsearch.status') }}
                    :
                </strong>
                {{ statusInfo.health.status }}
            </div>
        </div>
    </sw-card>
    <sw-card
        :title="$t('frosh-tools.tabs.elasticsearch.indices')"
        deprecated
        v-if="!isLoading && isActive"
        :large="true"
        positionIdentifier="frosh-tools-tab-elasticsearch-indices"
    >
        <template #toolbar>
            <!-- @todo: Make the refresh button fancy -->
            <sw-button
                deprecated
                variant="ghost"
                @click="createdComponent"
            >
                <sw-icon
                    :small="true"
                    deprecated
                    name="regular-undo"
                ></sw-icon>
            </sw-button>
        </template>

        <template #grid>
            <sw-data-grid
                v-if="indices"
                :showSelection="false"
                :dataSource="indices"
                :columns="columns"
            >
                <template #column-name="{ item }">
                    <sw-label
                        variant="primary"
                        appearance="pill"
                        v-if="item.aliases.length"
                    >
                        {{ $t('frosh-tools.active') }}
                    </sw-label>
                    {{ item.name }}
                    <br/>
                </template>

                <template #column-indexSize="{ item }">
                    {{ formatSize(item.indexSize) }}
                    <br/>
                </template>

                <template #actions="{ item }">
                    <sw-context-menu-item
                        variant="danger"
                        @click="deleteIndex(item.name)"
                    >
                        {{ $t('frosh-tools.delete') }}
                    </sw-context-menu-item>
                </template>
            </sw-data-grid>
        </template>
    </sw-card>
    <sw-card
        :title="$t('frosh-tools.actions')"
        v-if="!isLoading && isActive"
        deprecated
        :large="true"
        positionIdentifier="frosh-tools-tab-elasticsearch-health"
    >
        <sw-button
            deprecated
            @click="reindex"
            variant="primary"
        >
            {{ $t('frosh-tools.tabs.elasticsearch.action.reindex') }}
        </sw-button>
        <sw-button
            deprecated
            @click="switchAlias"
        >
            {{ $t('frosh-tools.tabs.elasticsearch.action.switchAlias') }}
        </sw-button>
        <sw-button
            deprecated
            @click="flushAll"
        >
            {{ $t('frosh-tools.tabs.elasticsearch.action.flushAll') }}
        </sw-button>
        <sw-button
            deprecated
            @click="cleanup"
        >
            {{ $t('frosh-tools.tabs.elasticsearch.action.cleanup') }}
        </sw-button>
        <sw-button
            deprecated
            @click="resetElasticsearch"
            variant="danger"
        >
            {{ $t('frosh-tools.tabs.elasticsearch.action.reset') }}
        </sw-button>
    </sw-card>
    <sw-card
        :title="$t('frosh-tools.tabs.elasticsearch.console.title')"
        deprecated
        v-if="!isLoading && isActive"
        :large="true"
        positionIdentifier="frosh-tools-tab-elasticsearch-console"
    >
        <sw-code-editor
            completionMode="text"
            mode="twig"
            :softWraps="true"
            :setFocus="false"
            :disabled="false"
            :sanitizeInput="false"
            v-model:value="consoleInput"
        ></sw-code-editor>
        <sw-button
            deprecated
            @click="onConsoleEnter"
        >
            {{ $t('frosh-tools.tabs.elasticsearch.console.send') }}
        </sw-button>
        <div>
            <strong>
                {{ $t('frosh-tools.tabs.elasticsearch.console.output') }}
                :
            </strong>
        </div>
        <pre>{{ consoleOutput }}</pre>
    </sw-card>
</sw-card-view>`;var{Mixin:Te,Component:Re}=Shopware;Re.register("frosh-tools-tab-elasticsearch",{template:V,inject:["froshElasticSearch"],mixins:[Te.getByName("notification")],data(){return{isLoading:!0,isActive:!0,statusInfo:{},indices:[],consoleInput:"GET /_cat/indices",consoleOutput:{}}},computed:{columns(){return[{property:"name",label:"frosh-tools.name",rawData:!0,primary:!0},{property:"indexSize",label:"frosh-tools.size",rawData:!0,primary:!0},{property:"docs",label:"frosh-tools.docs",rawData:!0,primary:!0}]}},created(){this.createdComponent()},methods:{async createdComponent(){this.isLoading=!0;try{this.statusInfo=await this.froshElasticSearch.status()}catch{this.isActive=!1,this.isLoading=!1;return}finally{this.isLoading=!1}this.indices=await this.froshElasticSearch.indices()},formatSize(e){let a=e;if(Math.abs(e)<1024)return e+" B";let r=["KiB","MiB","GiB","TiB","PiB","EiB","ZiB","YiB"],i=-1,o=10**1;do a/=1024,++i;while(Math.round(Math.abs(a)*o)/o>=1024&&i<r.length-1);return a.toFixed(1)+" "+r[i]},async deleteIndex(e){await this.froshElasticSearch.deleteIndex(e),await this.createdComponent()},async onConsoleEnter(){let e=this.consoleInput.split(`
`),t=e.shift(),s=e.join(`
`).trim(),[a,r]=t.split(" ");try{this.consoleOutput=await this.froshElasticSearch.console(a,r,s)}catch(i){this.consoleOutput=i.response.data}},async reindex(){await this.froshElasticSearch.reindex(),this.createNotificationSuccess({message:this.$t("global.default.success")}),await this.createdComponent()},async switchAlias(){await this.froshElasticSearch.switchAlias(),this.createNotificationSuccess({message:this.$t("global.default.success")}),await this.createdComponent()},async flushAll(){await this.froshElasticSearch.flushAll(),this.createNotificationSuccess({message:this.$t("global.default.success")}),await this.createdComponent()},async resetElasticsearch(){await this.froshElasticSearch.reset(),this.createNotificationSuccess({message:this.$t("global.default.success")}),await this.createdComponent()},async cleanup(){await this.froshElasticSearch.cleanup(),this.createNotificationSuccess({message:this.$t("global.default.success")}),await this.createdComponent()}}});var G=`<sw-card-view>
    <sw-card
        class="frosh-tools-tab-feature-flags__feature-flags-card"
        :title="$tc('frosh-tools.tabs.feature-flags.title')"
        :isLoading="isLoading"
        :large="true"
    >
        <template #toolbar>
            <!-- @todo: Make the refresh button fancy -->
            <sw-button
                variant="ghost"
                deprecated
                @click="createdComponent"
            >
                <sw-icon
                    deprecated
                    :small="true"
                    name="regular-undo"
                ></sw-icon>
            </sw-button>
        </template>
        <sw-data-grid
            :showSelection="false"
            :dataSource="featureFlags"
            :columns="columns"
            :showActions="false"
        >
            <template #column-active="{ item }">
                <sw-icon
                    v-if="item.active"
                    color="#37d046"
                    name="regular-checkmark-s"
                    small
                />
                <sw-icon
                    v-else
                    color="#de294c"
                    name="regular-plus-xs"
                    small
                />
            </template>

            <template #column-major="{ item }">
                <sw-icon
                    v-if="item.major"
                    color="#37d046"
                    name="regular-checkmark-s"
                    small
                />
                <sw-icon
                    v-else
                    color="#de294c"
                    name="regular-plus-xs"
                    small
                />
            </template>

            <template #column-default="{ item }">
                <sw-icon
                    v-if="item.default"
                    color="#37d046"
                    name="regular-checkmark-s"
                    small
                />
                <sw-icon
                    v-else
                    color="#de294c"
                    name="regular-plus-xs"
                    small
                />
            </template>
        </sw-data-grid>
    </sw-card>
</sw-card-view>`;var{Component:Me,Mixin:Ee}=Shopware;Me.register("frosh-tools-tab-feature-flags",{template:G,inject:["froshToolsService"],mixins:[Ee.getByName("notification")],data(){return{featureFlags:null,isLoading:!0}},created(){this.createdComponent()},computed:{columns(){return[{property:"flag",label:"frosh-tools.tabs.feature-flags.flag",rawData:!0},{property:"active",label:"frosh-tools.active",rawData:!0},{property:"description",label:"frosh-tools.tabs.feature-flags.description",rawData:!0},{property:"major",label:"frosh-tools.tabs.feature-flags.major",rawData:!0},{property:"default",label:"frosh-tools.tabs.feature-flags.default",rawData:!0}]}},methods:{async refresh(){await this.createdComponent()},async createdComponent(){this.isLoading=!0,this.featureFlags=await this.froshToolsService.getFeatureFlags(),this.isLoading=!1}}});var W=`<sw-card-view>
    <sw-card
        class="frosh-tools-tab-logs__logs-card"
        :title="$t('frosh-tools.tabs.logs.title')"
        :isLoading="isLoading"
        :large="true"
        positionIdentifier="frosh-tools-tab-logs"
        deprecated
    >
        <template #toolbar>
            <sw-button
                variant="ghost"
                deprecated
                @click="refresh"
            >
                <sw-icon
                    :small="true"
                    name="regular-undo"
                    deprecated
                ></sw-icon>
            </sw-button>
            <sw-single-select
                :options="logFiles"
                :isLoading="isLoading"
                :placeholder="$t('frosh-tools.tabs.logs.logFileSelect.placeholder')"
                labelProperty="name"
                valueProperty="name"
                v-model:value="selectedLogFile"
                @update:value="onFileSelected"
            ></sw-single-select>
        </template>

        <template #grid>
            <sw-data-grid
                :showSelection="false"
                :showActions="false"
                :dataSource="logEntries"
                :columns="columns"
            >
                <template #column-date="{ item }">
                    {{ date(item.date, {hour: '2-digit', minute: '2-digit', second: '2-digit'}) }}
                </template>

                <template #column-message="{ item }">
                    <a @click="showInfoModal(item)">{{ item.message }}</a>
                </template>
            </sw-data-grid>
            <sw-pagination
                :total="totalLogEntries"
                :limit="limit"
                :page="page"
                @page-change="onPageChange"
            ></sw-pagination>
        </template>
    </sw-card>
    <sw-modal
        v-if="displayedLog"
        variant="large"
    >
        <template #modal-header>
            <div class="sw-modal__titles">
                <h4 class="sw-modal__title">
                    {{ displayedLog.channel }}
                    -
                    {{ displayedLog.level }}
                </h4>
                <h5 class="sw-modal__subtitle">
                    {{ date(displayedLog.date, {hour: '2-digit', minute: '2-digit', second: '2-digit'}) }}
                </h5>
            </div>
            <button
                class="sw-modal__close"
                :title="$t('global.sw-modal.labelClose')"
                :aria-label="$t('global.sw-modal.labelClose')"
                @click="closeInfoModal"
            >
                <sw-icon
                    name="regular-times-s"
                    small
                    deprecated
                />
            </button>
        </template>
        <div>{{ displayedLog.message }}</div>
    </sw-modal>
</sw-card-view>`;var{Component:De,Mixin:Pe}=Shopware;De.register("frosh-tools-tab-logs",{template:W,inject:["froshToolsService"],mixins:[Pe.getByName("notification")],data(){return{logFiles:[],selectedLogFile:null,logEntries:[],totalLogEntries:0,limit:25,page:1,isLoading:!0,displayedLog:null}},created(){this.createdComponent()},computed:{columns(){return[{property:"date",label:"frosh-tools.date",rawData:!0},{property:"channel",label:"frosh-tools.channel",rawData:!0},{property:"level",label:"frosh-tools.level",rawData:!0},{property:"message",label:"frosh-tools.message",rawData:!0}]},date(){return Shopware.Filter.getByName("date")}},methods:{async refresh(){this.isLoading=!0,await this.createdComponent(),await this.loadLogEntries()},async createdComponent(){this.logFiles=await this.froshToolsService.getLogFiles(),this.isLoading=!1},async onFileSelected(){this.page=1,await this.loadLogEntries()},async loadLogEntries(){if(!this.selectedLogFile)return;let e=await this.froshToolsService.getLogFile(this.selectedLogFile,(this.page-1)*this.limit,this.limit);this.logEntries=e.data,this.totalLogEntries=parseInt(e.headers["file-size"],10)},async onPageChange(e){this.page=e.page,this.limit=e.limit,await this.loadLogEntries()},showInfoModal(e){this.displayedLog=e},closeInfoModal(){this.displayedLog=null}}});var K=`<sw-card-view>
    <sw-card
        class="frosh-tools-tab-state-machines__state-machines-card"
        deprecated
        :title="$t('frosh-tools.tabs.state-machines.title')"
        :isLoading="isLoading"
        :large="true"
        positionIdentifier="frosh-tools-tab-state-machines"
    >
        <div
            class="frosh-tools-tab-state-machines__state-machines-card-image-wrapper"
        >
            <img
                id="state_machine"
                class="frosh-tools-tab-state-machines__state-machines-card-image"
                type="image/svg+xml"
                src="/bundles/administration/static/img/empty-states/media-empty-state.svg"
                alt="State Machine"
                width="100%"
                height="auto"
                style="text-align:center; display:inline-block; opacity:0;"
            />
        </div>
        <template #toolbar>
            <sw-entity-single-select
                v-model="selectedStateMachine"
                entity="state_machine"
                :aside="true"
                @update:value="onStateMachineChange"
                :label="$t('frosh-tools.tabs.state-machines.label')"
                :placeholder="$t('frosh-tools.chooseStateMachine')"
                :helpText="$t('frosh-tools.tabs.state-machines.helpText')"
            />
        </template>
    </sw-card>
</sw-card-view>`;var{Component:Fe,Mixin:Ie}=Shopware;Fe.register("frosh-tools-tab-state-machines",{template:K,inject:["froshToolsService"],mixins:[Ie.getByName("notification")],data(){return{selectedStateMachine:null,image:null,isLoading:!0}},created(){this.createdComponent()},methods:{createdComponent(){this.isLoading=!1},async onStateMachineChange(e){if(!e)return;let t=await this.froshToolsService.stateMachines(e),s=document.getElementById("state_machine");"svg"in t?(this.image=t.svg,s.src=this.image,s.style.opacity="1",s.style.width="100%",s.style.height="auto"):s.style.opacity="0"}}});var Z=`<sw-card-view>
    <sw-card
        class="frosh-tools-tab-files__files-card"
        :class="isLoadingClass"
        :title="$t('frosh-tools.tabs.files.title')"
        :isLoading="isLoading"
        :large="true"
        positionIdentifier="frosh-tools-tab-files__files-card"
        deprecated
    >
        <sw-alert
            variant="error"
            deprecated
            v-if="items.error"
        >{{ items.error }}</sw-alert>
        <sw-alert
            variant="success"
            deprecated
            v-if="items.ok"
        >
            {{ $t('frosh-tools.tabs.files.allFilesOk') }}
        </sw-alert>
        <sw-alert
            variant="warning"
            v-else-if="items.files"
            deprecated
        >
            {{ $t('frosh-tools.tabs.files.notOk') }}
        </sw-alert>
        <template #toolbar>
            <!-- @todo: Make the refresh button fancy -->
            <sw-button
                variant="ghost"
                deprecated
                @click="refresh"
            >
                <sw-icon
                    :small="true"
                    deprecated
                    name="regular-undo"
                ></sw-icon>
            </sw-button>
        </template>
        <sw-data-grid
            v-if="items.files && items.files.length"
            :showSelection="false"
            :dataSource="items.files"
            :columns="columns"
        >
            <template #column-name="{ item }">
                <a
                    @click="diff(item)"
                    :title="$t('frosh-tools.tabs.files.restore.diff')"
                >{{ item.name }}</a>
            </template>

            <template #column-expected="{ item }">
                <span v-if="item.expected">
                    {{ $t('frosh-tools.tabs.files.expectedProject') }}
                </span>
                <span v-else>
                    {{ $t('frosh-tools.tabs.files.expectedAll') }}
                </span>
            </template>

            <template #actions="{ item }">
                <sw-context-menu-item @click="openUrl(item.shopwareUrl)">
                    {{ $t('frosh-tools.tabs.files.openOriginal') }}
                </sw-context-menu-item>
                <sw-context-menu-item @click="diff(item)">
                    {{ $t('frosh-tools.tabs.files.restore.diff') }}
                </sw-context-menu-item>
            </template>
        </sw-data-grid>
    </sw-card>
    <sw-modal
        v-if="showModal"
        variant="large"
        @modal-close="closeModal"
        :title="diffData.file.name"
    >
        <span
            style="white-space: pre"
            v-html="diffData.html"
        ></span>
        <template #modal-footer>
            <sw-button
                variant="ghost-danger"
                deprecated
                @click="restoreFile(diffData.file.name)"
                :disabled="diffData.file.expected"
            >
                <sw-icon
                    name="regular-exclamation-triangle"
                    deprecated
                ></sw-icon>
                {{ $t('frosh-tools.tabs.files.restore.restoreFile') }}
            </sw-button>
        </template>
    </sw-modal>
    <sw-card
        class="frosh-tools-tab-files__extension-files-card"
        :class="isLoadingClass"
        :title="$t('frosh-tools.tabs.extensionFiles.title')"
        :isLoading="isLoading"
        :large="true"
        positionIdentifier="frosh-tools-tab-files__extension-files-card"
    >
        <sw-alert
            variant="success"
            v-if="extensionItems.success"
        >
            {{ $t('frosh-tools.tabs.extensionFiles.allFilesOk') }}
        </sw-alert>
        <sw-alert
            variant="warning"
            v-else
        >
            {{ $t('frosh-tools.tabs.extensionFiles.notOk') }}
        </sw-alert>
        <template #toolbar>
            <!-- @todo: Make the refresh button fancy -->
            <sw-button
                variant="ghost"
                @click="refresh"
            >
                <sw-icon
                    :small="true"
                    name="regular-undo"
                ></sw-icon>
            </sw-button>
        </template>
        <div
            v-for="(extensionResult, extensionName) in extensionItems.extensionResults"
        >
            <h4>{{ extensionName }}</h4>
            <sw-alert
                variant="warning"
                v-if="extensionResult.wrongExtensionVersion"
            >
                {{ $t('frosh-tools.tabs.extensionFiles.wrongExtensionVersion') }}
            </sw-alert>
            <sw-alert
                variant="error"
                v-if="extensionResult.checkFailed"
            >
                {{ $t('frosh-tools.tabs.extensionFiles.checkFailed') }}
            </sw-alert>
            <sw-description-list v-else>
                <dt v-if="extensionResult.newFiles.length > 0">
                    {{ $t('frosh-tools.tabs.extensionFiles.newFiles') }}
                </dt>
                <dd v-for="file in extensionResult.newFiles">
                    {{ file }}
                </dd>
                <dt v-if="extensionResult.changedFiles.length > 0">
                    {{ $t('frosh-tools.tabs.extensionFiles.changedFiles') }}
                </dt>
                <dd v-for="file in extensionResult.changedFiles">
                    {{ file }}
                </dd>
                <dt v-if="extensionResult.missingFiles.length > 0">
                    {{ $t('frosh-tools.tabs.extensionFiles.missingFiles') }}
                </dt>
                <dd v-for="file in extensionResult.missingFiles">
                    {{ file }}
                </dd>
            </sw-description-list>
        </div>
    </sw-card>
</sw-card-view>`;var Y=le(X()),{Component:xe,Mixin:He}=Shopware;xe.register("frosh-tools-tab-files",{template:Z,inject:["repositoryFactory","froshToolsService"],mixins:[He.getByName("notification")],data(){return{items:{},extensionItems:{},isLoading:!0,diffData:{html:"",file:""},showModal:!1}},created(){this.createdComponent()},computed:{columns(){return[{property:"name",label:"frosh-tools.name",rawData:!0,primary:!0},{property:"expected",label:"frosh-tools.status",rawData:!0,primary:!0}]},isLoadingClass(){return{"is-loading":this.isLoading}}},methods:{async refresh(){this.isLoading=!0,await this.createdComponent()},async createdComponent(){this.items=(await this.froshToolsService.getShopwareFiles()).data,this.extensionItems=(await this.froshToolsService.getExtensionFiles()).data,this.isLoading=!1},openUrl(e){window.open(e,"_blank")},async diff(e){this.isLoading=!0;let t=(await this.froshToolsService.getFileContents(e.name)).data,s=new Y.default,a=s.diff_main(t.originalContent,t.content);s.diff_cleanupSemantic(a),this.diffData.html=s.diff_prettyHtml(a).replace(new RegExp("background:#e6ffe6;","g"),"background:#ABF2BC;").replace(new RegExp("background:#ffe6e6;","g"),"background:rgba(255,129,130,0.4);"),this.diffData.file=e,this.openModal(),this.isLoading=!1},async restoreFile(e){this.closeModal(),this.isLoading=!0;let t=await this.froshToolsService.restoreShopwareFile(e);t.data.status?this.createNotificationSuccess({message:t.data.status}):this.createNotificationError({message:t.data.error}),await this.refresh()},openModal(){this.showModal=!0},closeModal(){this.showModal=!1}}});var J=`<div>
    <sw-card
        deprecated
        :title="$t('frosh-tools.tabs.fastly.statsTitle')"
        :large="true"
        positionIdentifier="frosh-tools-fastly-stats"
    >
        <template #toolbar>
            <sw-single-select
                :placeholder="$t('frosh-tools.tabs.fastly.timeframeLabel')"
                :options="timeframeOptions"
                v-model:value="timeframe"
                @update:value="loadStats"
                :showSearch="false"
                size="small"
            ></sw-single-select>
        </template>
        <sw-card-section v-if="!stats">
            <sw-empty-state
                :title="$t('frosh-tools.tabs.fastly.emptyStats')"
                icon="regular-chart-line"
                :absolute="false"
            ></sw-empty-state>
        </sw-card-section>
        <sw-card-section v-else>
            <sw-container
                columns="1fr 1fr 1fr 1fr"
                gap="20px"
            >
                <div class="frosh-tools-stat">
                    <div class="frosh-tools-stat__label">
                        {{ $t('frosh-tools.tabs.fastly.requests') }}
                    </div>
                    <div class="frosh-tools-stat__value">
                        {{ formatNumber(stats.requests) }}
                    </div>
                </div>
                <div class="frosh-tools-stat">
                    <div class="frosh-tools-stat__label">
                        {{ $t('frosh-tools.tabs.fastly.hitRate') }}
                    </div>
                    <div class="frosh-tools-stat__value">
                        {{ formatNumber(stats.hit_ratio * 100) }}
                        %
                    </div>
                </div>
                <div class="frosh-tools-stat">
                    <div class="frosh-tools-stat__label">
                        {{ $t('frosh-tools.tabs.fastly.bandwidth') }}
                    </div>
                    <div class="frosh-tools-stat__value">
                        {{ formatSize(stats.bandwidth) }}
                    </div>
                </div>
                <div class="frosh-tools-stat">
                    <div class="frosh-tools-stat__label">
                        {{ $t('frosh-tools.tabs.fastly.hits') }}
                    </div>
                    <div class="frosh-tools-stat__value">{{ formatNumber(stats.hits) }}</div>
                </div>
            </sw-container>
        </sw-card-section>
    </sw-card>
    <sw-card
        deprecated
        :title="$t('frosh-tools.tabs.fastly.title')"
        :large="true"
        positionIdentifier="frosh-tools-fastly-purge"
    >
        <sw-card-section>
            <sw-container
                columns="1fr"
                gap="20px"
            >
                <p>
                    {{ $t('frosh-tools.tabs.fastly.description') }}
                </p>
                <sw-button
                    variant="primary"
                    @click="onPurgeAll"
                >
                    {{ $t('frosh-tools.tabs.fastly.purgeAll') }}
                </sw-button>
            </sw-container>
        </sw-card-section>
        <sw-card-section divider="top">
            <sw-container
                columns="1fr 150px"
                gap="20px"
                align="end"
            >
                <sw-text-field
                    v-model="purgePath"
                    :placeholder="$t('frosh-tools.tabs.fastly.placeholderPath')"
                    :label="$t('frosh-tools.tabs.fastly.purgePathLabel')"
                    class="fastly-purge-url-field"
                ></sw-text-field>
                <sw-button
                    variant="primary"
                    @click="onPurge"
                >
                    {{ $t('frosh-tools.tabs.fastly.purge') }}
                </sw-button>
            </sw-container>
        </sw-card-section>
    </sw-card>
    <sw-card
        deprecated
        :title="$t('frosh-tools.tabs.fastly.snippets.title')"
        v-if="snippets && snippets.length > 0"
        :large="true"
        positionIdentifier="frosh-tools-fastly-snippet"
    >
        <sw-data-grid
            :dataSource="snippets"
            :columns="snippetColumns"
            :showSelection="false"
        >
            <template #actions="{ item }">
                <sw-context-menu-item @click="activeSnippet = item">
                    {{ $t('frosh-tools.tabs.fastly.snippets.view') }}
                </sw-context-menu-item>
            </template>
        </sw-data-grid>
    </sw-card>
    <sw-modal
        v-if="activeSnippet"
        :title="activeSnippet.name"
        @modal-close="activeSnippet = null"
        variant="large"
    >
        <sw-code-editor
            class="fastly-code-editor"
            :value="activeSnippet.content"
            :readonly="true"
            mode="text"
            :softwraps="true"
            :setfocus="false"
        ></sw-code-editor>
        <template #modal-footer>
            <sw-button @click="activeSnippet = null">
                {{ $t('global.default.close') }}
            </sw-button>
        </template>
    </sw-modal>
</div>`;var{Component:ze,Mixin:je}=Shopware;ze.register("frosh-tools-tab-fastly",{template:J,inject:["froshToolsService"],mixins:[je.getByName("notification")],data(){return{purgePath:"",isLoading:!1,stats:null,snippets:[],activeSnippet:null,timeframe:"2h",numberFormater:null}},computed:{timeframeOptions(){return[{value:"30m",label:this.$t("frosh-tools.tabs.fastly.timeframes.30m")},{value:"1h",label:this.$t("frosh-tools.tabs.fastly.timeframes.1h")},{value:"2h",label:this.$t("frosh-tools.tabs.fastly.timeframes.2h")},{value:"24h",label:this.$t("frosh-tools.tabs.fastly.timeframes.24h")},{value:"7d",label:this.$t("frosh-tools.tabs.fastly.timeframes.7d")},{value:"30d",label:this.$t("frosh-tools.tabs.fastly.timeframes.30d")}]},snippetColumns(){return[{property:"name",label:this.$t("frosh-tools.tabs.fastly.snippets.name"),allowResize:!0},{property:"type",label:this.$t("frosh-tools.tabs.fastly.snippets.type"),allowResize:!0},{property:"priority",label:this.$t("frosh-tools.tabs.fastly.snippets.priority"),allowResize:!0}]}},created(){let e=Shopware.Application.getContainer("factory").locale.getLastKnownLocale();this.numberFormater=new Intl.NumberFormat(e,{minimumFractionDigits:2,maximumFractionDigits:2}),this.loadStats(),this.loadSnippets()},methods:{async loadStats(){this.stats=await this.froshToolsService.getFastlyStatistics(this.timeframe)},async loadSnippets(){this.snippets=await this.froshToolsService.getFastlySnippets()},formatSize(e){if(e>1024*1024*1024){let s=e/1073741824;return this.numberFormater.format(s)+" GiB"}let t=e/(1024*1024);return this.numberFormater.format(t)+" MiB"},formatNumber(e){return this.numberFormater.format(e)},async onPurgeAll(){this.isLoading=!0;try{await this.froshToolsService.fastlyPurgeAll(),this.createNotificationSuccess({message:this.$t("frosh-tools.tabs.fastly.purgeAllSuccess")})}catch{this.createNotificationError({message:this.$t("frosh-tools.tabs.fastly.purgeAllError")})}finally{this.isLoading=!1}},async onPurge(){if(this.purgePath){this.isLoading=!0;try{await this.froshToolsService.fastlyPurge(this.purgePath),this.createNotificationSuccess({message:this.$t("frosh-tools.tabs.fastly.purgeSuccess")}),this.purgePath=""}catch{this.createNotificationError({message:this.$t("frosh-tools.tabs.fastly.purgeError")})}finally{this.isLoading=!1}}}}});var ee=`<sw-page class="frosh-tools">
    <template #content>
        <sw-tabs
            :small="false"
            positionIdentifier="frosh-tools-tabs"
        >
            <sw-tabs-item :route="{ name: 'frosh.tools.index.index' }">
                {{ $t('frosh-tools.tabs.index.title') }}
            </sw-tabs-item>
            <sw-tabs-item :route="{ name: 'frosh.tools.index.cache' }">
                {{ $t('frosh-tools.tabs.cache.title') }}
            </sw-tabs-item>
            <sw-tabs-item :route="{ name: 'frosh.tools.index.scheduled' }">
                {{ $t('frosh-tools.tabs.scheduledTaskOverview.title') }}
            </sw-tabs-item>
            <sw-tabs-item :route="{ name: 'frosh.tools.index.queue' }">
                {{ $t('frosh-tools.tabs.queue.title') }}
            </sw-tabs-item>
            <sw-tabs-item :route="{ name: 'frosh.tools.index.logs' }">
                {{ $t('frosh-tools.tabs.logs.title') }}
            </sw-tabs-item>
            <sw-tabs-item
                :route="{ name: 'frosh.tools.index.elasticsearch' }"
                v-if="elasticsearchAvailable"
            >
                {{ $t('frosh-tools.tabs.elasticsearch.title') }}
            </sw-tabs-item>
            <sw-tabs-item :route="{ name: 'frosh.tools.index.featureflags' }">
                {{ $t('frosh-tools.tabs.feature-flags.title') }}
            </sw-tabs-item>
            <sw-tabs-item :route="{ name: 'frosh.tools.index.files' }">
                {{ $t('frosh-tools.tabs.files.title') }}
            </sw-tabs-item>
            <sw-tabs-item :route="{ name: 'frosh.tools.index.statemachines' }">
                {{ $t('frosh-tools.tabs.state-machines.title') }}
            </sw-tabs-item>
            <sw-tabs-item
                :route="{ name: 'frosh.tools.index.fastly' }"
                v-if="fastlyAvailable"
            >
                {{ $t('frosh-tools.tabs.fastly.title') }}
            </sw-tabs-item>
        </sw-tabs>
        <router-view></router-view>
    </template>
</sw-page>`;var{Component:Ue}=Shopware;Ue.register("frosh-tools-index",{template:ee,inject:["froshToolsService"],computed:{fastlyAvailable(){try{return Shopware.Store.get("context").app.config.settings?.froshTools.fastlyEnabled||!1}catch{return Shopware.State.get("context").app.config.settings?.froshTools.fastlyEnabled||!1}},elasticsearchAvailable(){try{return Shopware.Store.get("context").app.config.settings?.froshTools.elasticsearchEnabled||!1}catch{return Shopware.State.get("context").app.config.settings?.froshTools.elasticsearchEnabled||!1}}}});Shopware.Service("privileges").addPrivilegeMappingEntry({category:"additional_permissions",parent:null,key:"frosh_tools",roles:{frosh_tools:{privileges:["frosh_tools:read"],dependencies:[]}}});Shopware.Module.register("frosh-tools",{type:"plugin",name:"frosh-tools.title",title:"frosh-tools.title",description:"",color:"#303A4F",icon:"regular-cog",routes:{index:{component:"frosh-tools-index",path:"index",children:{index:{component:"frosh-tools-tab-index",path:"index",meta:{privilege:"frosh_tools:read",parentPath:"sw.settings.index.plugins"}},cache:{component:"frosh-tools-tab-cache",path:"cache",meta:{privilege:"frosh_tools:read",parentPath:"sw.settings.index.plugins"}},queue:{component:"frosh-tools-tab-queue",path:"queue",meta:{privilege:"frosh_tools:read",parentPath:"sw.settings.index.plugins"}},scheduled:{component:"frosh-tools-tab-scheduled",path:"scheduled",meta:{privilege:"frosh_tools:read",parentPath:"sw.settings.index.plugins"}},elasticsearch:{component:"frosh-tools-tab-elasticsearch",path:"elasticsearch",meta:{privilege:"frosh_tools:read",parentPath:"sw.settings.index.plugins"}},featureflags:{component:"frosh-tools-tab-feature-flags",path:"feature-flags",meta:{privilege:"frosh_tools:read",parentPath:"frosh.tools.index.index"}},logs:{component:"frosh-tools-tab-logs",path:"logs",meta:{privilege:"frosh_tools:read",parentPath:"sw.settings.index.plugins"}},files:{component:"frosh-tools-tab-files",path:"files",meta:{privilege:"frosh_tools:read",parentPath:"sw.settings.index.plugins"}},statemachines:{component:"frosh-tools-tab-state-machines",path:"state-machines",meta:{privilege:"frosh_tools:read",parentPath:"sw.settings.index.plugins"}},fastly:{component:"frosh-tools-tab-fastly",path:"fastly",meta:{privilege:"frosh_tools:read",parentPath:"sw.settings.index.plugins"}}}}},settingsItem:[{group:"plugins",to:"frosh.tools.index.cache",icon:"regular-cog",name:"frosh-tools",label:"frosh-tools.title",privilege:"frosh_tools:read"}]});})();
