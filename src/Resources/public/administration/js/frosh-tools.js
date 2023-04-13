(()=>{var ee=Object.create;var I=Object.defineProperty;var te=Object.getOwnPropertyDescriptor;var se=Object.getOwnPropertyNames;var ae=Object.getPrototypeOf,re=Object.prototype.hasOwnProperty;var ie=(e,t)=>()=>(t||e((t={exports:{}}).exports,t),t.exports);var ne=(e,t,s,a)=>{if(t&&typeof t=="object"||typeof t=="function")for(let i of se(t))!re.call(e,i)&&i!==s&&I(e,i,{get:()=>t[i],enumerable:!(a=te(t,i))||a.enumerable});return e};var oe=(e,t,s)=>(s=e!=null?ee(ae(e)):{},ne(t||!e||!e.__esModule?I(s,"default",{value:e,enumerable:!0}):s,e));var Z=ie((kt,L)=>{var h=function(){this.Diff_Timeout=1,this.Diff_EditCost=4,this.Match_Threshold=.5,this.Match_Distance=1e3,this.Patch_DeleteThreshold=.5,this.Patch_Margin=4,this.Match_MaxBits=32},b=-1,_=1,d=0;h.Diff=function(e,t){return[e,t]};h.prototype.diff_main=function(e,t,s,a){typeof a>"u"&&(this.Diff_Timeout<=0?a=Number.MAX_VALUE:a=new Date().getTime()+this.Diff_Timeout*1e3);var i=a;if(e==null||t==null)throw new Error("Null input. (diff_main)");if(e==t)return e?[new h.Diff(d,e)]:[];typeof s>"u"&&(s=!0);var r=s,n=this.diff_commonPrefix(e,t),o=e.substring(0,n);e=e.substring(n),t=t.substring(n),n=this.diff_commonSuffix(e,t);var l=e.substring(e.length-n);e=e.substring(0,e.length-n),t=t.substring(0,t.length-n);var c=this.diff_compute_(e,t,r,i);return o&&c.unshift(new h.Diff(d,o)),l&&c.push(new h.Diff(d,l)),this.diff_cleanupMerge(c),c};h.prototype.diff_compute_=function(e,t,s,a){var i;if(!e)return[new h.Diff(_,t)];if(!t)return[new h.Diff(b,e)];var r=e.length>t.length?e:t,n=e.length>t.length?t:e,o=r.indexOf(n);if(o!=-1)return i=[new h.Diff(_,r.substring(0,o)),new h.Diff(d,n),new h.Diff(_,r.substring(o+n.length))],e.length>t.length&&(i[0][0]=i[2][0]=b),i;if(n.length==1)return[new h.Diff(b,e),new h.Diff(_,t)];var l=this.diff_halfMatch_(e,t);if(l){var c=l[0],f=l[1],u=l[2],m=l[3],g=l[4],p=this.diff_main(c,u,s,a),v=this.diff_main(f,m,s,a);return p.concat([new h.Diff(d,g)],v)}return s&&e.length>100&&t.length>100?this.diff_lineMode_(e,t,a):this.diff_bisect_(e,t,a)};h.prototype.diff_lineMode_=function(e,t,s){var a=this.diff_linesToChars_(e,t);e=a.chars1,t=a.chars2;var i=a.lineArray,r=this.diff_main(e,t,!1,s);this.diff_charsToLines_(r,i),this.diff_cleanupSemantic(r),r.push(new h.Diff(d,""));for(var n=0,o=0,l=0,c="",f="";n<r.length;){switch(r[n][0]){case _:l++,f+=r[n][1];break;case b:o++,c+=r[n][1];break;case d:if(o>=1&&l>=1){r.splice(n-o-l,o+l),n=n-o-l;for(var u=this.diff_main(c,f,!1,s),m=u.length-1;m>=0;m--)r.splice(n,0,u[m]);n=n+u.length}l=0,o=0,c="",f="";break}n++}return r.pop(),r};h.prototype.diff_bisect_=function(e,t,s){for(var a=e.length,i=t.length,r=Math.ceil((a+i)/2),n=r,o=2*r,l=new Array(o),c=new Array(o),f=0;f<o;f++)l[f]=-1,c[f]=-1;l[n+1]=0,c[n+1]=0;for(var u=a-i,m=u%2!=0,g=0,p=0,v=0,k=0,w=0;w<r&&!(new Date().getTime()>s);w++){for(var S=-w+g;S<=w-p;S+=2){var y=n+S,C;S==-w||S!=w&&l[y-1]<l[y+1]?C=l[y+1]:C=l[y-1]+1;for(var T=C-S;C<a&&T<i&&e.charAt(C)==t.charAt(T);)C++,T++;if(l[y]=C,C>a)p+=2;else if(T>i)g+=2;else if(m){var D=n+u-S;if(D>=0&&D<o&&c[D]!=-1){var M=a-c[D];if(C>=M)return this.diff_bisectSplit_(e,t,C,T,s)}}}for(var A=-w+v;A<=w-k;A+=2){var D=n+A,M;A==-w||A!=w&&c[D-1]<c[D+1]?M=c[D+1]:M=c[D-1]+1;for(var E=M-A;M<a&&E<i&&e.charAt(a-M-1)==t.charAt(i-E-1);)M++,E++;if(c[D]=M,M>a)k+=2;else if(E>i)v+=2;else if(!m){var y=n+u-A;if(y>=0&&y<o&&l[y]!=-1){var C=l[y],T=n+C-y;if(M=a-M,C>=M)return this.diff_bisectSplit_(e,t,C,T,s)}}}}return[new h.Diff(b,e),new h.Diff(_,t)]};h.prototype.diff_bisectSplit_=function(e,t,s,a,i){var r=e.substring(0,s),n=t.substring(0,a),o=e.substring(s),l=t.substring(a),c=this.diff_main(r,n,!1,i),f=this.diff_main(o,l,!1,i);return c.concat(f)};h.prototype.diff_linesToChars_=function(e,t){var s=[],a={};s[0]="";function i(l){for(var c="",f=0,u=-1,m=s.length;u<l.length-1;){u=l.indexOf(`
`,f),u==-1&&(u=l.length-1);var g=l.substring(f,u+1);(a.hasOwnProperty?a.hasOwnProperty(g):a[g]!==void 0)?c+=String.fromCharCode(a[g]):(m==r&&(g=l.substring(f),u=l.length),c+=String.fromCharCode(m),a[g]=m,s[m++]=g),f=u+1}return c}var r=4e4,n=i(e);r=65535;var o=i(t);return{chars1:n,chars2:o,lineArray:s}};h.prototype.diff_charsToLines_=function(e,t){for(var s=0;s<e.length;s++){for(var a=e[s][1],i=[],r=0;r<a.length;r++)i[r]=t[a.charCodeAt(r)];e[s][1]=i.join("")}};h.prototype.diff_commonPrefix=function(e,t){if(!e||!t||e.charAt(0)!=t.charAt(0))return 0;for(var s=0,a=Math.min(e.length,t.length),i=a,r=0;s<i;)e.substring(r,i)==t.substring(r,i)?(s=i,r=s):a=i,i=Math.floor((a-s)/2+s);return i};h.prototype.diff_commonSuffix=function(e,t){if(!e||!t||e.charAt(e.length-1)!=t.charAt(t.length-1))return 0;for(var s=0,a=Math.min(e.length,t.length),i=a,r=0;s<i;)e.substring(e.length-i,e.length-r)==t.substring(t.length-i,t.length-r)?(s=i,r=s):a=i,i=Math.floor((a-s)/2+s);return i};h.prototype.diff_commonOverlap_=function(e,t){var s=e.length,a=t.length;if(s==0||a==0)return 0;s>a?e=e.substring(s-a):s<a&&(t=t.substring(0,s));var i=Math.min(s,a);if(e==t)return i;for(var r=0,n=1;;){var o=e.substring(i-n),l=t.indexOf(o);if(l==-1)return r;n+=l,(l==0||e.substring(i-n)==t.substring(0,n))&&(r=n,n++)}};h.prototype.diff_halfMatch_=function(e,t){if(this.Diff_Timeout<=0)return null;var s=e.length>t.length?e:t,a=e.length>t.length?t:e;if(s.length<4||a.length*2<s.length)return null;var i=this;function r(p,v,k){for(var w=p.substring(k,k+Math.floor(p.length/4)),S=-1,y="",C,T,D,M;(S=v.indexOf(w,S+1))!=-1;){var A=i.diff_commonPrefix(p.substring(k),v.substring(S)),E=i.diff_commonSuffix(p.substring(0,k),v.substring(0,S));y.length<E+A&&(y=v.substring(S-E,S)+v.substring(S,S+A),C=p.substring(0,k-E),T=p.substring(k+A),D=v.substring(0,S-E),M=v.substring(S+A))}return y.length*2>=p.length?[C,T,D,M,y]:null}var n=r(s,a,Math.ceil(s.length/4)),o=r(s,a,Math.ceil(s.length/2)),l;if(!n&&!o)return null;o?n?l=n[4].length>o[4].length?n:o:l=o:l=n;var c,f,u,m;e.length>t.length?(c=l[0],f=l[1],u=l[2],m=l[3]):(u=l[0],m=l[1],c=l[2],f=l[3]);var g=l[4];return[c,f,u,m,g]};h.prototype.diff_cleanupSemantic=function(e){for(var t=!1,s=[],a=0,i=null,r=0,n=0,o=0,l=0,c=0;r<e.length;)e[r][0]==d?(s[a++]=r,n=l,o=c,l=0,c=0,i=e[r][1]):(e[r][0]==_?l+=e[r][1].length:c+=e[r][1].length,i&&i.length<=Math.max(n,o)&&i.length<=Math.max(l,c)&&(e.splice(s[a-1],0,new h.Diff(b,i)),e[s[a-1]+1][0]=_,a--,a--,r=a>0?s[a-1]:-1,n=0,o=0,l=0,c=0,i=null,t=!0)),r++;for(t&&this.diff_cleanupMerge(e),this.diff_cleanupSemanticLossless(e),r=1;r<e.length;){if(e[r-1][0]==b&&e[r][0]==_){var f=e[r-1][1],u=e[r][1],m=this.diff_commonOverlap_(f,u),g=this.diff_commonOverlap_(u,f);m>=g?(m>=f.length/2||m>=u.length/2)&&(e.splice(r,0,new h.Diff(d,u.substring(0,m))),e[r-1][1]=f.substring(0,f.length-m),e[r+1][1]=u.substring(m),r++):(g>=f.length/2||g>=u.length/2)&&(e.splice(r,0,new h.Diff(d,f.substring(0,g))),e[r-1][0]=_,e[r-1][1]=u.substring(0,u.length-g),e[r+1][0]=b,e[r+1][1]=f.substring(g),r++),r++}r++}};h.prototype.diff_cleanupSemanticLossless=function(e){function t(g,p){if(!g||!p)return 6;var v=g.charAt(g.length-1),k=p.charAt(0),w=v.match(h.nonAlphaNumericRegex_),S=k.match(h.nonAlphaNumericRegex_),y=w&&v.match(h.whitespaceRegex_),C=S&&k.match(h.whitespaceRegex_),T=y&&v.match(h.linebreakRegex_),D=C&&k.match(h.linebreakRegex_),M=T&&g.match(h.blanklineEndRegex_),A=D&&p.match(h.blanklineStartRegex_);return M||A?5:T||D?4:w&&!y&&C?3:y||C?2:w||S?1:0}for(var s=1;s<e.length-1;){if(e[s-1][0]==d&&e[s+1][0]==d){var a=e[s-1][1],i=e[s][1],r=e[s+1][1],n=this.diff_commonSuffix(a,i);if(n){var o=i.substring(i.length-n);a=a.substring(0,a.length-n),i=o+i.substring(0,i.length-n),r=o+r}for(var l=a,c=i,f=r,u=t(a,i)+t(i,r);i.charAt(0)===r.charAt(0);){a+=i.charAt(0),i=i.substring(1)+r.charAt(0),r=r.substring(1);var m=t(a,i)+t(i,r);m>=u&&(u=m,l=a,c=i,f=r)}e[s-1][1]!=l&&(l?e[s-1][1]=l:(e.splice(s-1,1),s--),e[s][1]=c,f?e[s+1][1]=f:(e.splice(s+1,1),s--))}s++}};h.nonAlphaNumericRegex_=/[^a-zA-Z0-9]/;h.whitespaceRegex_=/\s/;h.linebreakRegex_=/[\r\n]/;h.blanklineEndRegex_=/\n\r?\n$/;h.blanklineStartRegex_=/^\r?\n\r?\n/;h.prototype.diff_cleanupEfficiency=function(e){for(var t=!1,s=[],a=0,i=null,r=0,n=!1,o=!1,l=!1,c=!1;r<e.length;)e[r][0]==d?(e[r][1].length<this.Diff_EditCost&&(l||c)?(s[a++]=r,n=l,o=c,i=e[r][1]):(a=0,i=null),l=c=!1):(e[r][0]==b?c=!0:l=!0,i&&(n&&o&&l&&c||i.length<this.Diff_EditCost/2&&n+o+l+c==3)&&(e.splice(s[a-1],0,new h.Diff(b,i)),e[s[a-1]+1][0]=_,a--,i=null,n&&o?(l=c=!0,a=0):(a--,r=a>0?s[a-1]:-1,l=c=!1),t=!0)),r++;t&&this.diff_cleanupMerge(e)};h.prototype.diff_cleanupMerge=function(e){e.push(new h.Diff(d,""));for(var t=0,s=0,a=0,i="",r="",n;t<e.length;)switch(e[t][0]){case _:a++,r+=e[t][1],t++;break;case b:s++,i+=e[t][1],t++;break;case d:s+a>1?(s!==0&&a!==0&&(n=this.diff_commonPrefix(r,i),n!==0&&(t-s-a>0&&e[t-s-a-1][0]==d?e[t-s-a-1][1]+=r.substring(0,n):(e.splice(0,0,new h.Diff(d,r.substring(0,n))),t++),r=r.substring(n),i=i.substring(n)),n=this.diff_commonSuffix(r,i),n!==0&&(e[t][1]=r.substring(r.length-n)+e[t][1],r=r.substring(0,r.length-n),i=i.substring(0,i.length-n))),t-=s+a,e.splice(t,s+a),i.length&&(e.splice(t,0,new h.Diff(b,i)),t++),r.length&&(e.splice(t,0,new h.Diff(_,r)),t++),t++):t!==0&&e[t-1][0]==d?(e[t-1][1]+=e[t][1],e.splice(t,1)):t++,a=0,s=0,i="",r="";break}e[e.length-1][1]===""&&e.pop();var o=!1;for(t=1;t<e.length-1;)e[t-1][0]==d&&e[t+1][0]==d&&(e[t][1].substring(e[t][1].length-e[t-1][1].length)==e[t-1][1]?(e[t][1]=e[t-1][1]+e[t][1].substring(0,e[t][1].length-e[t-1][1].length),e[t+1][1]=e[t-1][1]+e[t+1][1],e.splice(t-1,1),o=!0):e[t][1].substring(0,e[t+1][1].length)==e[t+1][1]&&(e[t-1][1]+=e[t+1][1],e[t][1]=e[t][1].substring(e[t+1][1].length)+e[t+1][1],e.splice(t+1,1),o=!0)),t++;o&&this.diff_cleanupMerge(e)};h.prototype.diff_xIndex=function(e,t){var s=0,a=0,i=0,r=0,n;for(n=0;n<e.length&&(e[n][0]!==_&&(s+=e[n][1].length),e[n][0]!==b&&(a+=e[n][1].length),!(s>t));n++)i=s,r=a;return e.length!=n&&e[n][0]===b?r:r+(t-i)};h.prototype.diff_prettyHtml=function(e){for(var t=[],s=/&/g,a=/</g,i=/>/g,r=/\n/g,n=0;n<e.length;n++){var o=e[n][0],l=e[n][1],c=l.replace(s,"&amp;").replace(a,"&lt;").replace(i,"&gt;").replace(r,"&para;<br>");switch(o){case _:t[n]='<ins style="background:#e6ffe6;">'+c+"</ins>";break;case b:t[n]='<del style="background:#ffe6e6;">'+c+"</del>";break;case d:t[n]="<span>"+c+"</span>";break}}return t.join("")};h.prototype.diff_text1=function(e){for(var t=[],s=0;s<e.length;s++)e[s][0]!==_&&(t[s]=e[s][1]);return t.join("")};h.prototype.diff_text2=function(e){for(var t=[],s=0;s<e.length;s++)e[s][0]!==b&&(t[s]=e[s][1]);return t.join("")};h.prototype.diff_levenshtein=function(e){for(var t=0,s=0,a=0,i=0;i<e.length;i++){var r=e[i][0],n=e[i][1];switch(r){case _:s+=n.length;break;case b:a+=n.length;break;case d:t+=Math.max(s,a),s=0,a=0;break}}return t+=Math.max(s,a),t};h.prototype.diff_toDelta=function(e){for(var t=[],s=0;s<e.length;s++)switch(e[s][0]){case _:t[s]="+"+encodeURI(e[s][1]);break;case b:t[s]="-"+e[s][1].length;break;case d:t[s]="="+e[s][1].length;break}return t.join("	").replace(/%20/g," ")};h.prototype.diff_fromDelta=function(e,t){for(var s=[],a=0,i=0,r=t.split(/\t/g),n=0;n<r.length;n++){var o=r[n].substring(1);switch(r[n].charAt(0)){case"+":try{s[a++]=new h.Diff(_,decodeURI(o))}catch{throw new Error("Illegal escape in diff_fromDelta: "+o)}break;case"-":case"=":var l=parseInt(o,10);if(isNaN(l)||l<0)throw new Error("Invalid number in diff_fromDelta: "+o);var c=e.substring(i,i+=l);r[n].charAt(0)=="="?s[a++]=new h.Diff(d,c):s[a++]=new h.Diff(b,c);break;default:if(r[n])throw new Error("Invalid diff operation in diff_fromDelta: "+r[n])}}if(i!=e.length)throw new Error("Delta length ("+i+") does not equal source text length ("+e.length+").");return s};h.prototype.match_main=function(e,t,s){if(e==null||t==null||s==null)throw new Error("Null input. (match_main)");return s=Math.max(0,Math.min(s,e.length)),e==t?0:e.length?e.substring(s,s+t.length)==t?s:this.match_bitap_(e,t,s):-1};h.prototype.match_bitap_=function(e,t,s){if(t.length>this.Match_MaxBits)throw new Error("Pattern too long for this browser.");var a=this.match_alphabet_(t),i=this;function r(C,T){var D=C/t.length,M=Math.abs(s-T);return i.Match_Distance?D+M/i.Match_Distance:M?1:D}var n=this.Match_Threshold,o=e.indexOf(t,s);o!=-1&&(n=Math.min(r(0,o),n),o=e.lastIndexOf(t,s+t.length),o!=-1&&(n=Math.min(r(0,o),n)));var l=1<<t.length-1;o=-1;for(var c,f,u=t.length+e.length,m,g=0;g<t.length;g++){for(c=0,f=u;c<f;)r(g,s+f)<=n?c=f:u=f,f=Math.floor((u-c)/2+c);u=f;var p=Math.max(1,s-f+1),v=Math.min(s+f,e.length)+t.length,k=Array(v+2);k[v+1]=(1<<g)-1;for(var w=v;w>=p;w--){var S=a[e.charAt(w-1)];if(g===0?k[w]=(k[w+1]<<1|1)&S:k[w]=(k[w+1]<<1|1)&S|((m[w+1]|m[w])<<1|1)|m[w+1],k[w]&l){var y=r(g,w-1);if(y<=n)if(n=y,o=w-1,o>s)p=Math.max(1,2*s-o);else break}}if(r(g+1,s)>n)break;m=k}return o};h.prototype.match_alphabet_=function(e){for(var t={},s=0;s<e.length;s++)t[e.charAt(s)]=0;for(var s=0;s<e.length;s++)t[e.charAt(s)]|=1<<e.length-s-1;return t};h.prototype.patch_addContext_=function(e,t){if(t.length!=0){if(e.start2===null)throw Error("patch not initialized");for(var s=t.substring(e.start2,e.start2+e.length1),a=0;t.indexOf(s)!=t.lastIndexOf(s)&&s.length<this.Match_MaxBits-this.Patch_Margin-this.Patch_Margin;)a+=this.Patch_Margin,s=t.substring(e.start2-a,e.start2+e.length1+a);a+=this.Patch_Margin;var i=t.substring(e.start2-a,e.start2);i&&e.diffs.unshift(new h.Diff(d,i));var r=t.substring(e.start2+e.length1,e.start2+e.length1+a);r&&e.diffs.push(new h.Diff(d,r)),e.start1-=i.length,e.start2-=i.length,e.length1+=i.length+r.length,e.length2+=i.length+r.length}};h.prototype.patch_make=function(e,t,s){var a,i;if(typeof e=="string"&&typeof t=="string"&&typeof s>"u")a=e,i=this.diff_main(a,t,!0),i.length>2&&(this.diff_cleanupSemantic(i),this.diff_cleanupEfficiency(i));else if(e&&typeof e=="object"&&typeof t>"u"&&typeof s>"u")i=e,a=this.diff_text1(i);else if(typeof e=="string"&&t&&typeof t=="object"&&typeof s>"u")a=e,i=t;else if(typeof e=="string"&&typeof t=="string"&&s&&typeof s=="object")a=e,i=s;else throw new Error("Unknown call format to patch_make.");if(i.length===0)return[];for(var r=[],n=new h.patch_obj,o=0,l=0,c=0,f=a,u=a,m=0;m<i.length;m++){var g=i[m][0],p=i[m][1];switch(!o&&g!==d&&(n.start1=l,n.start2=c),g){case _:n.diffs[o++]=i[m],n.length2+=p.length,u=u.substring(0,c)+p+u.substring(c);break;case b:n.length1+=p.length,n.diffs[o++]=i[m],u=u.substring(0,c)+u.substring(c+p.length);break;case d:p.length<=2*this.Patch_Margin&&o&&i.length!=m+1?(n.diffs[o++]=i[m],n.length1+=p.length,n.length2+=p.length):p.length>=2*this.Patch_Margin&&o&&(this.patch_addContext_(n,f),r.push(n),n=new h.patch_obj,o=0,f=u,l=c);break}g!==_&&(l+=p.length),g!==b&&(c+=p.length)}return o&&(this.patch_addContext_(n,f),r.push(n)),r};h.prototype.patch_deepCopy=function(e){for(var t=[],s=0;s<e.length;s++){var a=e[s],i=new h.patch_obj;i.diffs=[];for(var r=0;r<a.diffs.length;r++)i.diffs[r]=new h.Diff(a.diffs[r][0],a.diffs[r][1]);i.start1=a.start1,i.start2=a.start2,i.length1=a.length1,i.length2=a.length2,t[s]=i}return t};h.prototype.patch_apply=function(e,t){if(e.length==0)return[t,[]];e=this.patch_deepCopy(e);var s=this.patch_addPadding(e);t=s+t+s,this.patch_splitMax(e);for(var a=0,i=[],r=0;r<e.length;r++){var n=e[r].start2+a,o=this.diff_text1(e[r].diffs),l,c=-1;if(o.length>this.Match_MaxBits?(l=this.match_main(t,o.substring(0,this.Match_MaxBits),n),l!=-1&&(c=this.match_main(t,o.substring(o.length-this.Match_MaxBits),n+o.length-this.Match_MaxBits),(c==-1||l>=c)&&(l=-1))):l=this.match_main(t,o,n),l==-1)i[r]=!1,a-=e[r].length2-e[r].length1;else{i[r]=!0,a=l-n;var f;if(c==-1?f=t.substring(l,l+o.length):f=t.substring(l,c+this.Match_MaxBits),o==f)t=t.substring(0,l)+this.diff_text2(e[r].diffs)+t.substring(l+o.length);else{var u=this.diff_main(o,f,!1);if(o.length>this.Match_MaxBits&&this.diff_levenshtein(u)/o.length>this.Patch_DeleteThreshold)i[r]=!1;else{this.diff_cleanupSemanticLossless(u);for(var m=0,g,p=0;p<e[r].diffs.length;p++){var v=e[r].diffs[p];v[0]!==d&&(g=this.diff_xIndex(u,m)),v[0]===_?t=t.substring(0,l+g)+v[1]+t.substring(l+g):v[0]===b&&(t=t.substring(0,l+g)+t.substring(l+this.diff_xIndex(u,m+v[1].length))),v[0]!==b&&(m+=v[1].length)}}}}}return t=t.substring(s.length,t.length-s.length),[t,i]};h.prototype.patch_addPadding=function(e){for(var t=this.Patch_Margin,s="",a=1;a<=t;a++)s+=String.fromCharCode(a);for(var a=0;a<e.length;a++)e[a].start1+=t,e[a].start2+=t;var i=e[0],r=i.diffs;if(r.length==0||r[0][0]!=d)r.unshift(new h.Diff(d,s)),i.start1-=t,i.start2-=t,i.length1+=t,i.length2+=t;else if(t>r[0][1].length){var n=t-r[0][1].length;r[0][1]=s.substring(r[0][1].length)+r[0][1],i.start1-=n,i.start2-=n,i.length1+=n,i.length2+=n}if(i=e[e.length-1],r=i.diffs,r.length==0||r[r.length-1][0]!=d)r.push(new h.Diff(d,s)),i.length1+=t,i.length2+=t;else if(t>r[r.length-1][1].length){var n=t-r[r.length-1][1].length;r[r.length-1][1]+=s.substring(0,n),i.length1+=n,i.length2+=n}return s};h.prototype.patch_splitMax=function(e){for(var t=this.Match_MaxBits,s=0;s<e.length;s++)if(!(e[s].length1<=t)){var a=e[s];e.splice(s--,1);for(var i=a.start1,r=a.start2,n="";a.diffs.length!==0;){var o=new h.patch_obj,l=!0;for(o.start1=i-n.length,o.start2=r-n.length,n!==""&&(o.length1=o.length2=n.length,o.diffs.push(new h.Diff(d,n)));a.diffs.length!==0&&o.length1<t-this.Patch_Margin;){var c=a.diffs[0][0],f=a.diffs[0][1];c===_?(o.length2+=f.length,r+=f.length,o.diffs.push(a.diffs.shift()),l=!1):c===b&&o.diffs.length==1&&o.diffs[0][0]==d&&f.length>2*t?(o.length1+=f.length,i+=f.length,l=!1,o.diffs.push(new h.Diff(c,f)),a.diffs.shift()):(f=f.substring(0,t-o.length1-this.Patch_Margin),o.length1+=f.length,i+=f.length,c===d?(o.length2+=f.length,r+=f.length):l=!1,o.diffs.push(new h.Diff(c,f)),f==a.diffs[0][1]?a.diffs.shift():a.diffs[0][1]=a.diffs[0][1].substring(f.length))}n=this.diff_text2(o.diffs),n=n.substring(n.length-this.Patch_Margin);var u=this.diff_text1(a.diffs).substring(0,this.Patch_Margin);u!==""&&(o.length1+=u.length,o.length2+=u.length,o.diffs.length!==0&&o.diffs[o.diffs.length-1][0]===d?o.diffs[o.diffs.length-1][1]+=u:o.diffs.push(new h.Diff(d,u))),l||e.splice(++s,0,o)}}};h.prototype.patch_toText=function(e){for(var t=[],s=0;s<e.length;s++)t[s]=e[s];return t.join("")};h.prototype.patch_fromText=function(e){var t=[];if(!e)return t;for(var s=e.split(`
`),a=0,i=/^@@ -(\d+),?(\d*) \+(\d+),?(\d*) @@$/;a<s.length;){var r=s[a].match(i);if(!r)throw new Error("Invalid patch string: "+s[a]);var n=new h.patch_obj;for(t.push(n),n.start1=parseInt(r[1],10),r[2]===""?(n.start1--,n.length1=1):r[2]=="0"?n.length1=0:(n.start1--,n.length1=parseInt(r[2],10)),n.start2=parseInt(r[3],10),r[4]===""?(n.start2--,n.length2=1):r[4]=="0"?n.length2=0:(n.start2--,n.length2=parseInt(r[4],10)),a++;a<s.length;){var o=s[a].charAt(0);try{var l=decodeURI(s[a].substring(1))}catch{throw new Error("Illegal escape in patch_fromText: "+l)}if(o=="-")n.diffs.push(new h.Diff(b,l));else if(o=="+")n.diffs.push(new h.Diff(_,l));else if(o==" ")n.diffs.push(new h.Diff(d,l));else{if(o=="@")break;if(o!=="")throw new Error('Invalid patch mode "'+o+'" in: '+l)}a++}}return t};h.patch_obj=function(){this.diffs=[],this.start1=null,this.start2=null,this.length1=0,this.length2=0};h.patch_obj.prototype.toString=function(){var e,t;this.length1===0?e=this.start1+",0":this.length1==1?e=this.start1+1:e=this.start1+1+","+this.length1,this.length2===0?t=this.start2+",0":this.length2==1?t=this.start2+1:t=this.start2+1+","+this.length2;for(var s=["@@ -"+e+" +"+t+` @@
`],a,i=0;i<this.diffs.length;i++){switch(this.diffs[i][0]){case _:a="+";break;case b:a="-";break;case d:a=" ";break}s[i+1]=a+encodeURI(this.diffs[i][1])+`
`}return s.join("").replace(/%20/g," ")};L.exports=h;L.exports.diff_match_patch=h;L.exports.DIFF_DELETE=b;L.exports.DIFF_INSERT=_;L.exports.DIFF_EQUAL=d});var{ApiService:$}=Shopware.Classes,P=class extends ${constructor(t,s,a="_action/frosh-tools"){super(t,s,a)}getCacheInfo(){let t=`${this.getApiBasePath()}/cache`;return this.httpClient.get(t,{headers:this.getBasicHeaders()}).then(s=>$.handleResponse(s))}clearCache(t){let s=`${this.getApiBasePath()}/cache/${t}`;return this.httpClient.delete(s,{headers:this.getBasicHeaders()}).then(a=>$.handleResponse(a))}getQueue(){let t=`${this.getApiBasePath()}/queue/list`;return this.httpClient.get(t,{headers:this.getBasicHeaders()}).then(s=>$.handleResponse(s))}resetQueue(){let t=`${this.getApiBasePath()}/queue`;return this.httpClient.delete(t,{headers:this.getBasicHeaders()}).then(s=>$.handleResponse(s))}runScheduledTask(t){let s=`${this.getApiBasePath()}/scheduled-task/${t}`;return this.httpClient.post(s,{},{headers:this.getBasicHeaders()}).then(a=>$.handleResponse(a))}scheduledTasksRegister(){let t=`${this.getApiBasePath()}/scheduled-tasks/register`;return this.httpClient.post(t,{},{headers:this.getBasicHeaders()}).then(s=>$.handleResponse(s))}healthStatus(){let t=`${this.getApiBasePath()}/health/status`;return this.httpClient.get(t,{headers:this.getBasicHeaders()}).then(s=>$.handleResponse(s))}performanceStatus(){let t=`${this.getApiBasePath()}/performance/status`;return this.httpClient.get(t,{headers:this.getBasicHeaders()}).then(s=>$.handleResponse(s))}getLogFiles(){let t=`${this.getApiBasePath()}/logs/files`;return this.httpClient.get(t,{headers:this.getBasicHeaders()}).then(s=>$.handleResponse(s))}getLogFile(t,s=0,a=20){let i=`${this.getApiBasePath()}/logs/file`;return this.httpClient.get(i,{params:{file:t,offset:s,limit:a},headers:this.getBasicHeaders()}).then(r=>r)}getShopwareFiles(){let t=`${this.getApiBasePath()}/shopware-files`;return this.httpClient.get(t,{headers:this.getBasicHeaders()}).then(s=>s)}getFileContents(t){let s=`${this.getApiBasePath()}/file-contents`;return this.httpClient.get(s,{params:{file:t},headers:this.getBasicHeaders()}).then(a=>a)}restoreShopwareFile(t){let s=`${this.getApiBasePath()}/shopware-file/restore`;return this.httpClient.get(s,{params:{file:t},headers:this.getBasicHeaders()}).then(a=>a)}getFeatureFlags(){let t=`${this.getApiBasePath()}/feature-flag/list`;return this.httpClient.get(t,{headers:this.getBasicHeaders()}).then(s=>$.handleResponse(s))}toggleFeatureFlag(t){let s=`${this.getApiBasePath()}/feature-flag/toggle`;return this.httpClient.post(s,{flag:t},{headers:this.getBasicHeaders()}).then(a=>$.handleResponse(a))}stateMachines(t){let s=`${this.getApiBasePath()}/state-machines/load`;return this.httpClient.get(s,{params:{stateMachine:t},headers:this.getBasicHeaders()}).then(a=>$.handleResponse(a))}},N=P;var{ApiService:R}=Shopware.Classes,F=class extends R{constructor(t,s,a="_action/frosh-tools/elasticsearch"){super(t,s,a)}status(){let t=`${this.getApiBasePath()}/status`;return this.httpClient.get(t,{headers:this.getBasicHeaders()}).then(s=>R.handleResponse(s))}indices(){let t=`${this.getApiBasePath()}/indices`;return this.httpClient.get(t,{headers:this.getBasicHeaders()}).then(s=>R.handleResponse(s))}deleteIndex(t){let s=`${this.getApiBasePath()}/index/`+t;return this.httpClient.delete(s,{headers:this.getBasicHeaders()}).then(a=>R.handleResponse(a))}console(t,s,a){let i=`${this.getApiBasePath()}/console`+s;return this.httpClient.request({url:i,method:t,headers:{...this.getBasicHeaders(),"content-type":"application/json"},data:a}).then(r=>R.handleResponse(r))}flushAll(){let t=`${this.getApiBasePath()}/flush_all`;return this.httpClient.post(t,{},{headers:this.getBasicHeaders()}).then(s=>R.handleResponse(s))}reindex(){let t=`${this.getApiBasePath()}/reindex`;return this.httpClient.post(t,{},{headers:this.getBasicHeaders()}).then(s=>R.handleResponse(s))}switchAlias(){let t=`${this.getApiBasePath()}/switch_alias`;return this.httpClient.post(t,{},{headers:this.getBasicHeaders()}).then(s=>R.handleResponse(s))}cleanup(){let t=`${this.getApiBasePath()}/cleanup`;return this.httpClient.post(t,{},{headers:this.getBasicHeaders()}).then(s=>R.handleResponse(s))}reset(){let t=`${this.getApiBasePath()}/reset`;return this.httpClient.post(t,{},{headers:this.getBasicHeaders()}).then(s=>R.handleResponse(s))}},H=F;var{Application:B}=Shopware;B.addServiceProvider("froshToolsService",e=>{let t=B.getContainer("init");return new N(t.httpClient,e.loginService)});B.addServiceProvider("froshElasticSearch",e=>{let t=B.getContainer("init");return new H(t.httpClient,e.loginService)});var O=`{% block sw_data_grid_inline_edit_type_unknown %}
    <sw-datepicker
        v-else-if="column.inlineEdit === 'date'"
        dateType="date"
        v-model="currentValue">
    </sw-datepicker>

    <sw-datepicker
        v-else-if="column.inlineEdit === 'datetime'"
        dateType="datetime"
        v-model="currentValue">
    </sw-datepicker>

    {% parent() %}
{% endblock %}
`;var{Component:he}=Shopware;he.override("sw-data-grid-inline-edit",{template:O});var z=`{% block sw_version_status %}
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
            <sw-color-badge v-if="health && hasPermission" :variant="healthVariant" :rounded="true"></sw-color-badge>
            <template  v-else>
                {% parent %}
            </template>
        {% endblock %}
    </router-link>
    <template  v-else>
        {% parent %}
    </template>
{% endblock %}
`;var{Component:fe}=Shopware;fe.override("sw-version",{template:z,inject:["froshToolsService","acl"],async created(){this.checkPermission()&&await this.checkHealth()},data(){return{health:null,hasPermission:!1}},computed:{healthVariant(){let e="success";for(let t of this.health){if(t.state==="STATE_ERROR"){e="error";continue}t.state==="STATE_WARNING"&&e==="success"&&(e="warning")}return e},healthPlaceholder(){let e="Shop Status: Ok";if(this.health===null)return e;for(let t of this.health){if(t.state==="STATE_ERROR"){e="Shop Status: May outage, Check System Status";continue}t.state==="STATE_WARNING"&&e==="Shop Status: Ok"&&(e="Shop Status: Issues, Check System Status")}return e}},methods:{async checkHealth(){this.health=await this.froshToolsService.healthStatus(),setInterval(async()=>{this.health=await this.froshToolsService.healthStatus()},3e4)},checkPermission(){return this.hasPermission=this.acl.can("frosh_tools:read")}}});var j=`<sw-card-view>
    <sw-card class="frosh-tools-tab-index__health-card" :isLoading="isLoading" :large="true">

        <template #toolbar>
            <!-- @todo: Make the refresh button fancy -->
            <sw-button variant="ghost" @click="refresh">
                <sw-icon :small="true" name="default-arrow-360-left"></sw-icon>
            </sw-button>
        </template>

        <sw-card class="frosh-tools-tab-index__health-card" :title="$tc('frosh-tools.tabs.index.title')" :large="true">
            <sw-data-grid
                v-if="health"
                :showSelection="false"
                :showActions="false"
                :dataSource="health"
                :columns="columns">

                <template #column-current="{ item }">
                    {{ item.current }}
                </template>

                <template #column-name="{ item }">
                    <template>
                        <sw-label variant="success" appearance="pill" v-if="item.state === 'STATE_OK'">
                            {{ $tc('frosh-tools.good') }}
                        </sw-label>
                        <sw-label variant="warning" appearance="pill" v-if="item.state === 'STATE_WARNING'">
                            {{ $tc('frosh-tools.warning') }}
                        </sw-label>
                        <sw-label variant="danger" appearance="pill" v-if="item.state === 'STATE_ERROR'">
                            {{ $tc('frosh-tools.error') }}
                        </sw-label>
                        <sw-label variant="info" appearance="pill" v-if="item.state === 'STATE_INFO'">
                            {{ $tc('frosh-tools.info') }}
                        </sw-label>
                    </template>

                    <template v-if="item.url">
                        <a :href="item.url" target="_blank">{{ item.snippet }}</a>
                    </template>
                    <template v-else>{{ item.snippet }}</template>
                </template>
            </sw-data-grid>
        </sw-card>

        <sw-card class="frosh-tools-tab-index__health-card" :title="$tc('frosh-tools.tabs.index.performance')" :large="true">
            <sw-data-grid
                v-if="performanceStatus"
                :showSelection="false"
                :showActions="false"
                :dataSource="performanceStatus"
                :columns="columns">

                <template #column-current="{ item }">
                    {{ item.current }}
                </template>

                <template #column-name="{ item }">
                    <template>
                        <sw-label variant="success" appearance="pill" v-if="item.state === 'STATE_OK'">
                            {{ $tc('frosh-tools.good') }}
                        </sw-label>
                        <sw-label variant="warning" appearance="pill" v-if="item.state === 'STATE_WARNING'">
                            {{ $tc('frosh-tools.warning') }}
                        </sw-label>
                        <sw-label variant="danger" appearance="pill" v-if="item.state === 'STATE_ERROR'">
                            {{ $tc('frosh-tools.error') }}
                        </sw-label>
                        <sw-label variant="info" appearance="pill" v-if="item.state === 'STATE_INFO'">
                            {{ $tc('frosh-tools.info') }}
                        </sw-label>
                    </template>

                    <template v-if="item.url">
                        <a :href="item.url" target="_blank">{{ item.snippet }}</a>
                    </template>
                    <template v-else>{{ item.snippet }}</template>
                </template>
            </sw-data-grid>
        </sw-card>
    </sw-card>
</sw-card-view>
`;var{Component:me}=Shopware;me.register("frosh-tools-tab-index",{inject:["froshToolsService"],template:j,data(){return{isLoading:!0,health:null,performanceStatus:null}},created(){this.createdComponent()},computed:{columns(){return[{property:"name",label:"frosh-tools.name",rawData:!0},{property:"current",label:"frosh-tools.current",rawData:!0},{property:"recommended",label:"frosh-tools.recommended",rawData:!0}]}},methods:{async refresh(){this.isLoading=!0,await this.createdComponent()},async createdComponent(){this.health=await this.froshToolsService.healthStatus(),this.performanceStatus=await this.froshToolsService.performanceStatus(),this.isLoading=!1}}});var U=`<sw-card-view>
    <sw-card class="frosh-tools-tab-cache__cache-card" :title="$tc('frosh-tools.tabs.cache.title')" :isLoading="isLoading" :large="true">
        <template #toolbar>
            <!-- @todo: Make the refresh button fancy -->
            <sw-button variant="ghost" @click="createdComponent"><sw-icon :small="true" name="default-arrow-360-left"></sw-icon></sw-button>
        </template>

        <sw-data-grid
            :showSelection="false"
            :dataSource="cacheFolders"
            :columns="columns"
        >

            <template #column-name="{ item }">
                <sw-label variant="success" appearance="pill" v-if="item.active" >
                    {{ $tc('frosh-tools.active') }}
                </sw-label>
                <sw-label variant="primary" appearance="pill" v-if="item.type" >
                    {{ item.type }}
                </sw-label>
                {{ item.name }}
            </template>

            <template #column-size="{ item }">
                <template v-if="item.size < 0">
                    unknown
                </template>
                <template v-else>
                    {{ formatSize(item.size) }}
                </template>
            </template>

            <template #column-freeSpace="{ item }">
                <template v-if="item.freeSpace < 0">
                    unknown
                </template>
                <template v-else>
                    {{ formatSize(item.freeSpace) }}
                </template>
            </template>

            <template #actions="{ item }">
                <sw-context-menu-item variant="danger" @click="clearCache(item)">
                    {{ $tc('frosh-tools.clear') }}
                </sw-context-menu-item>
            </template>
        </sw-data-grid>
    </sw-card>

    <sw-card class="frosh-tools-tab-cache__action-card" :title="$tc('frosh-tools.actions')" :isLoading="isLoading" :large="true">
        <sw-button variant="primary" @click="compileTheme">{{ $tc('frosh-tools.compileTheme') }}</sw-button>
    </sw-card>
</sw-card-view>
`;var{Component:de,Mixin:pe}=Shopware,{Criteria:we}=Shopware.Data;de.register("frosh-tools-tab-cache",{template:U,inject:["froshToolsService","repositoryFactory","themeService"],mixins:[pe.getByName("notification")],data(){return{cacheInfo:null,isLoading:!0,numberFormater:null}},created(){let e=Shopware.Application.getContainer("factory").locale.getLastKnownLocale();this.numberFormater=new Intl.NumberFormat(e,{minimumFractionDigits:2,maximumFractionDigits:2}),this.createdComponent()},computed:{columns(){return[{property:"name",label:"frosh-tools.name",rawData:!0},{property:"size",label:"frosh-tools.used",rawData:!0,align:"right"},{property:"freeSpace",label:"frosh-tools.free",rawData:!0,align:"right"}]},cacheFolders(){return this.cacheInfo===null?[]:this.cacheInfo},salesChannelRepository(){return this.repositoryFactory.create("sales_channel")}},methods:{async createdComponent(){this.isLoading=!0,this.cacheInfo=await this.froshToolsService.getCacheInfo(),this.isLoading=!1},formatSize(e){let t=e/1048576;return this.numberFormater.format(t)+" MiB"},async clearCache(e){this.isLoading=!0,await this.froshToolsService.clearCache(e.name),await this.createdComponent()},async compileTheme(){let e=new we;e.addAssociation("themes"),this.isLoading=!0;let t=await this.salesChannelRepository.search(e,Shopware.Context.api);for(let s of t){let a=s.extensions.themes.first();a&&(await this.themeService.assignTheme(a.id,s.id),this.createNotificationSuccess({message:`${s.translated.name}: ${this.$tc("frosh-tools.themeCompiled")}`}))}this.isLoading=!1}}});var Q=`<sw-card-view>
    <sw-card class="frosh-tools-tab-queue__manager-card" :title="$tc('frosh-tools.tabs.queue.title')" :isLoading="isLoading" :large="true">
        <template #toolbar>
            <sw-button variant="ghost" @click="refresh"><sw-icon :small="true" name="default-arrow-360-left"></sw-icon></sw-button>
            <sw-button variant="danger" @click="showResetModal = true">{{ $tc('frosh-tools.resetQueue') }}</sw-button>
        </template>

        <sw-data-grid
            :showSelection="false"
            :dataSource="queueEntries"
            :columns="columns"
            :showActions="false"
        >
        </sw-data-grid>
    </sw-card>

    <sw-modal v-if="showResetModal" :title="$tc('frosh-tools.tabs.queue.reset.modal.title')" variant="small" @modal-close="showResetModal = false">
        {{ $tc('frosh-tools.tabs.queue.reset.modal.description') }}

        <template #modal-footer>
            <sw-button @click="showResetModal = false">{{ $tc('global.default.cancel') }}</sw-button>
            <sw-button variant="danger" @click="resetQueue">{{ $tc('frosh-tools.tabs.queue.reset.modal.reset') }}</sw-button>
        </template>
    </sw-modal>
</sw-card-view>
`;var{Component:be,Mixin:_e}=Shopware;be.register("frosh-tools-tab-queue",{template:Q,inject:["repositoryFactory","froshToolsService"],mixins:[_e.getByName("notification")],data(){return{queueEntries:null,showResetModal:!1,isLoading:!0}},created(){this.createdComponent()},computed:{columns(){return[{property:"name",label:"Name",rawData:!0},{property:"size",label:"Size",rawData:!0}]}},methods:{async refresh(){this.isLoading=!0,await this.createdComponent()},async createdComponent(){this.queueEntries=await this.froshToolsService.getQueue();for(let e of this.queueEntries){let t=e.name.split("\\");e.name=t[t.length-1]}this.isLoading=!1},async resetQueue(){this.isLoading=!0,await this.froshToolsService.resetQueue(),this.showResetModal=!1,this.createdComponent(),this.createNotificationSuccess({message:this.$tc("frosh-tools.tabs.queue.reset.success")}),this.isLoading=!1}}});var q=`<sw-card-view>
    <sw-card class="frosh-tools-tab-scheduled__tasks-card" :title="$tc('frosh-tools.tabs.scheduledTaskOverview.title')" :isLoading="isLoading" :large="true">

        <template #toolbar>
            <sw-button variant="ghost" @click="refresh"><sw-icon :small="true" name="default-arrow-360-left"></sw-icon></sw-button>
            <sw-button variant="primary" @click="registerScheduledTasks">{{ $tc('frosh-tools.scheduledTasksRegisterStarted') }}</sw-button>
        </template>

        <sw-entity-listing
            :showSelection="false"
            :fullPage="false"
            :allowInlineEdit="true"
            :allowEdit="false"
            :allowDelete="false"
            :showActions="true"
            :repository="scheduledRepository"
            :items="items"
            :columns="columns">

            <template #column-lastExecutionTime="{ item }">
                {{ item.lastExecutionTime | date({hour: '2-digit', minute: '2-digit'}) }}
            </template>
            <template #column-nextExecutionTime="{ item, column, compact, isInlineEdit }">
                <sw-data-grid-inline-edit
                    v-if="isInlineEdit"
                    :column="column"
                    :compact="compact"
                    :value="item[column.property]"
                    @input="item[column.property] = $event">
                </sw-data-grid-inline-edit>

                <span v-else>
                     {{ item.nextExecutionTime | date({hour: '2-digit', minute: '2-digit'}) }}
                </span>
            </template>

            <template #actions="{ item }">
                <sw-context-menu-item variant="primary" @click="runTask(item)">
                    {{ $tc('frosh-tools.runManually') }}
                </sw-context-menu-item>
            </template>
        </sw-entity-listing>
    </sw-card>
</sw-card-view>
`;var{Component:ye,Mixin:Ce}=Shopware,{Criteria:G}=Shopware.Data;ye.register("frosh-tools-tab-scheduled",{template:q,inject:["repositoryFactory","froshToolsService"],mixins:[Ce.getByName("notification")],data(){return{items:null,showResetModal:!1,isLoading:!0}},created(){this.createdComponent()},computed:{scheduledRepository(){return this.repositoryFactory.create("scheduled_task")},columns(){return[{property:"name",label:"frosh-tools.name",rawData:!0,primary:!0},{property:"runInterval",label:"frosh-tools.interval",rawData:!0,inlineEdit:"number"},{property:"lastExecutionTime",label:"frosh-tools.lastExecutionTime",rawData:!0},{property:"nextExecutionTime",label:"frosh-tools.nextExecutionTime",rawData:!0,inlineEdit:"datetime"},{property:"status",label:"frosh-tools.status",rawData:!0}]}},methods:{async refresh(){this.isLoading=!0,await this.createdComponent()},async createdComponent(){let e=new G;e.addSorting(G.sort("nextExecutionTime","ASC")),this.items=await this.scheduledRepository.search(e,Shopware.Context.api),this.isLoading=!1},async runTask(e){this.isLoading=!0;try{this.createNotificationInfo({message:this.$tc("frosh-tools.scheduledTaskStarted",0,{name:e.name})}),await this.froshToolsService.runScheduledTask(e.id),this.createNotificationSuccess({message:this.$tc("frosh-tools.scheduledTaskSucceed",0,{name:e.name})})}catch{this.createNotificationError({message:this.$tc("frosh-tools.scheduledTaskFailed",0,{name:e.name})})}this.createdComponent()},async registerScheduledTasks(){this.isLoading=!0;try{this.createNotificationInfo({message:this.$tc("frosh-tools.scheduledTasksRegisterStarted")}),await this.froshToolsService.scheduledTasksRegister(),this.createNotificationSuccess({message:this.$tc("frosh-tools.scheduledTasksRegisterSucceed")})}catch{this.createNotificationError({message:this.$tc("frosh-tools.scheduledTasksRegisterFailed")})}this.createdComponent()}}});var V=`<sw-card-view>
    <sw-card :title="$tc('frosh-tools.tabs.elasticsearch.title')" :large="true" :isLoading="isLoading">
        <sw-alert variant="error" v-if="!isLoading && !isActive">Elasticsearch is not enabled</sw-alert>

        <div v-if="!isLoading && isActive">
            <div><strong>Elasticsearch version: </strong> {{ statusInfo.info.version.number }}</div>
            <div><strong>Nodes: </strong> {{ statusInfo.health.number_of_nodes }}</div>
            <div><strong>Cluster status: </strong> {{ statusInfo.health.status }}</div>
        </div>
    </sw-card>

    <sw-card title="Indices" v-if="!isLoading && isActive" :large="true">
        <template #toolbar>
            <!-- @todo: Make the refresh button fancy -->
            <sw-button variant="ghost" @click="createdComponent"><sw-icon :small="true" name="default-arrow-360-left"></sw-icon></sw-button>
        </template>

        <sw-data-grid
            v-if="indices"
            :showSelection="false"
            :dataSource="indices"
            :columns="columns">

            <template #column-name="{ item }">
                <sw-label variant="primary" appearance="pill" v-if="item.aliases.length">
                    {{ $tc('frosh-tools.active') }}
                </sw-label>

                {{ item.name }}<br>
            </template>

            <template #column-indexSize="{ item }">
                {{ formatSize(item.indexSize) }}<br>
            </template>

            <template #actions="{ item }">
                <sw-context-menu-item variant="danger" @click="deleteIndex(item.name)">
                    {{ $tc('frosh-tools.delete') }}
                </sw-context-menu-item>
            </template>
        </sw-data-grid>
    </sw-card>

    <sw-card title="Actions" v-if="!isLoading && isActive" :large="true">
        <sw-button @click="reindex" variant="primary">Reindex</sw-button>
        <sw-button @click="switchAlias">Trigger alias switching</sw-button>
        <sw-button @click="flushAll">Flush all indices</sw-button>

        <sw-button @click="cleanup">Cleanup unused Indices</sw-button>
        <sw-button @click="resetElasticsearch" variant="danger">Delete all indices</sw-button>
    </sw-card>

    <sw-card title="Elasticsearch Console" v-if="!isLoading && isActive" :large="true">
        <sw-code-editor
            completionMode="text"
            mode="twig"
            :softWraps="true"
            :setFocus="false"
            :disabled="false"
            :sanitizeInput="false"
            v-model="consoleInput"
        ></sw-code-editor>

        <sw-button @click="onConsoleEnter">Send</sw-button>

        <div><strong>Output:</strong></div>

        <pre>{{ consoleOutput }}</pre>
    </sw-card>
</sw-card-view>
`;var{Mixin:Me,Component:De}=Shopware;De.register("frosh-tools-tab-elasticsearch",{template:V,inject:["froshElasticSearch"],mixins:[Me.getByName("notification")],data(){return{isLoading:!0,isActive:!0,statusInfo:{},indices:[],consoleInput:"GET /_cat/indices",consoleOutput:{}}},computed:{columns(){return[{property:"name",label:"frosh-tools.name",rawData:!0,primary:!0},{property:"indexSize",label:"frosh-tools.size",rawData:!0,primary:!0},{property:"docs",label:"frosh-tools.docs",rawData:!0,primary:!0}]}},created(){this.createdComponent()},methods:{async createdComponent(){this.isLoading=!0;try{this.statusInfo=await this.froshElasticSearch.status()}catch{this.isActive=!1,this.isLoading=!1;return}finally{this.isLoading=!1}this.indices=await this.froshElasticSearch.indices()},formatSize(e){let a=e;if(Math.abs(e)<1024)return e+" B";let i=["KiB","MiB","GiB","TiB","PiB","EiB","ZiB","YiB"],r=-1,n=10**1;do a/=1024,++r;while(Math.round(Math.abs(a)*n)/n>=1024&&r<i.length-1);return a.toFixed(1)+" "+i[r]},async deleteIndex(e){await this.froshElasticSearch.deleteIndex(e),await this.createdComponent()},async onConsoleEnter(){let e=this.consoleInput.split(`
`),t=e.shift(),s=e.join(`
`).trim(),[a,i]=t.split(" ");try{this.consoleOutput=await this.froshElasticSearch.console(a,i,s)}catch(r){this.consoleOutput=r.response.data}},async reindex(){await this.froshElasticSearch.reindex(),this.createNotificationSuccess({message:this.$tc("global.default.success")}),await this.createdComponent()},async switchAlias(){await this.froshElasticSearch.switchAlias(),this.createNotificationSuccess({message:this.$tc("global.default.success")}),await this.createdComponent()},async flushAll(){await this.froshElasticSearch.flushAll(),this.createNotificationSuccess({message:this.$tc("global.default.success")}),await this.createdComponent()},async resetElasticsearch(){await this.froshElasticSearch.reset(),this.createNotificationSuccess({message:this.$tc("global.default.success")}),await this.createdComponent()},async cleanup(){await this.froshElasticSearch.cleanup(),this.createNotificationSuccess({message:this.$tc("global.default.success")}),await this.createdComponent()}}});var W=`<sw-card-view>
    <sw-card class="frosh-tools-tab-logs__logs-card" :title="$tc('frosh-tools.tabs.logs.title')" :isLoading="isLoading" :large="true">
        <template #toolbar>
            <!-- @todo: Make the refresh button fancy -->
            <sw-button variant="ghost" @click="refresh"><sw-icon :small="true" name="default-arrow-360-left"></sw-icon></sw-button>
            <sw-single-select
                :options="logFiles"
                :isLoading="isLoading"
                :placeholder="$tc('frosh-tools.tabs.logs.logFileSelect.placeholder')"
                labelProperty="name"
                valueProperty="name"
                v-model="selectedLogFile"
                @change="onFileSelected"
            ></sw-single-select>
        </template>

        <sw-data-grid
            :showSelection="false"
            :showActions="false"
            :dataSource="logEntries"
            :columns="columns">
            <template slot="column-date" slot-scope="{ item }">
                {{ item.date | date({hour: '2-digit', minute: '2-digit', second: '2-digit'}) }}
            </template>
            <template slot="column-message" slot-scope="{ item }">
                <a @click="showInfoModal(item)">{{ item.message | raw }}</a>
            </template>
        </sw-data-grid>

        <sw-pagination
            :total="totalLogEntries"
            :limit="limit"
            :page="page"
            @page-change="onPageChange"
        ></sw-pagination>
    </sw-card>

    <sw-modal v-if="displayedLog"
              variant="large">

        <template slot="modal-header">
            <div class="sw-modal__titles">
                <h4 class="sw-modal__title">
                    {{ displayedLog.channel }} - {{ displayedLog.level }}
                </h4>

                <h5 class="sw-modal__subtitle">
                    {{ displayedLog.date | date({hour: '2-digit', minute: '2-digit', second: '2-digit'}) }}
                </h5>
            </div>

            <button
                class="sw-modal__close"
                :title="$tc('global.sw-modal.labelClose')"
                :aria-label="$tc('global.sw-modal.labelClose')"
                @click="closeInfoModal"
            >
                <sw-icon
                    name="regular-times-s"
                    small
                />
            </button>
        </template>

        <div v-html="displayedLog.message"></div>
    </sw-modal>
</sw-card-view>
`;var{Component:Ae,Mixin:$e}=Shopware;Ae.register("frosh-tools-tab-logs",{template:W,inject:["froshToolsService"],mixins:[$e.getByName("notification")],data(){return{logFiles:[],selectedLogFile:null,logEntries:[],totalLogEntries:0,limit:25,page:1,isLoading:!0,displayedLog:null}},created(){this.createdComponent()},computed:{columns(){return[{property:"date",label:"frosh-tools.date",rawData:!0},{property:"channel",label:"frosh-tools.channel",rawData:!0},{property:"level",label:"frosh-tools.level",rawData:!0},{property:"message",label:"frosh-tools.message",rawData:!0}]}},methods:{async refresh(){this.isLoading=!0,await this.createdComponent(),await this.onFileSelected()},async createdComponent(){this.logFiles=await this.froshToolsService.getLogFiles(),this.isLoading=!1},async onFileSelected(){if(!this.selectedLogFile)return;let e=await this.froshToolsService.getLogFile(this.selectedLogFile,(this.page-1)*this.limit,this.limit);this.logEntries=e.data,this.totalLogEntries=parseInt(e.headers["file-size"])},async onPageChange(e){this.page=e.page,this.limit=e.limit,await this.onFileSelected()},showInfoModal(e){this.displayedLog=e},closeInfoModal(){this.displayedLog=null}}});var K=`<sw-card-view>
    <sw-card class="frosh-tools-tab-files__files-card" :class="isLoadingClass" :title="$tc('frosh-tools.tabs.files.title')" :isLoading="isLoading" :large="true">
        <sw-alert variant="error" v-if="items.error">{{ items.error }}</sw-alert>

        <sw-alert variant="success" v-if="items.ok">{{ $tc('frosh-tools.tabs.files.allFilesOk') }}</sw-alert>
        <sw-alert variant="warning" v-else-if="items.files">{{ $tc('frosh-tools.tabs.files.notOk') }}</sw-alert>

        <template #toolbar>
            <!-- @todo: Make the refresh button fancy -->
            <sw-button variant="ghost" @click="refresh"><sw-icon :small="true" name="default-arrow-360-left"></sw-icon></sw-button>
        </template>

        <sw-data-grid
            v-if="items.files && items.files.length"
            :showSelection="false"
            :dataSource="items.files"
            :columns="columns">

            <template #column-expected="{ item }">
                <span v-if="item.expected">{{ $tc('frosh-tools.tabs.files.expectedProject') }}</span>
                <span v-else>{{ $tc('frosh-tools.tabs.files.expectedAll') }}</span>
            </template>

            <template #actions="{ item }">
                <sw-context-menu-item @click="openUrl(item.shopwareUrl)">
                    {{ $tc('frosh-tools.tabs.files.openOriginal') }}
                </sw-context-menu-item>
                <sw-context-menu-item @click="diff(item)">
                    {{ $tc('frosh-tools.tabs.files.restore.diff') }}
                </sw-context-menu-item>
            </template>
        </sw-data-grid>
    </sw-card>

    <sw-modal v-if="showModal"
        variant="large"
        @modal-close="closeModal"
        :title="diffData.file">

        <span style="white-space: pre" v-html="diffData.html"></span>

        <template slot="modal-footer">
            <sw-button variant="ghost-danger" @click="restoreFile(diffData.file.name)" :disabled="diffData.file.expected">
                <sw-icon name="default-badge-warning"></sw-icon>
                {{ $tc('frosh-tools.tabs.files.restore.restoreFile') }}
            </sw-button>
        </template>
    </sw-modal>
</sw-card-view>
`;var X=oe(Z()),{Component:Ee,Mixin:Le}=Shopware;Ee.register("frosh-tools-tab-files",{template:K,inject:["repositoryFactory","froshToolsService"],mixins:[Le.getByName("notification")],data(){return{items:{},isLoading:!0,diffData:{html:"",file:""},showModal:!1}},created(){this.createdComponent()},computed:{columns(){return[{property:"name",label:"frosh-tools.name",rawData:!0,primary:!0},{property:"expected",label:"frosh-tools.status",rawData:!0,primary:!0}]},isLoadingClass(){return{"is-loading":this.isLoading}}},methods:{async refresh(){this.isLoading=!0,await this.createdComponent()},async createdComponent(){this.items=(await this.froshToolsService.getShopwareFiles()).data,this.isLoading=!1},openUrl(e){window.open(e,"_blank")},async diff(e){let t=(await this.froshToolsService.getFileContents(e.name)).data,s=new X.default,a=s.diff_main(t.originalContent,t.content);s.diff_cleanupSemantic(a),this.diffData.html=s.diff_prettyHtml(a).replace(new RegExp("background:#e6ffe6;","g"),"background:#ABF2BC;").replace(new RegExp("background:#ffe6e6;","g"),"background:rgba(255,129,130,0.4);"),this.diffData.file=e,this.openModal()},async restoreFile(e){this.closeModal(),this.isLoading=!0;let t=await this.froshToolsService.restoreShopwareFile(e);t.data.status?this.createNotificationSuccess({message:t.data.status}):this.createNotificationError({message:t.data.error}),await this.refresh()},openModal(){this.showModal=!0},closeModal(){this.showModal=!1}}});var Y=`<sw-card-view>
    <sw-card
        class="frosh-tools-tab-feature-flags__feature-flags-card"
        :title="$tc('frosh-tools.tabs.feature-flags.title')"
        :isLoading="isLoading"
        :large="true"
    >

        <template #toolbar>
            <!-- @todo: Make the refresh button fancy -->
            <sw-button variant="ghost" @click="refresh"><sw-icon :small="true" name="default-arrow-360-left"></sw-icon></sw-button>
        </template>

        <sw-data-grid
            :showSelection="false"
            :dataSource="featureFlags"
            :columns="columns"
        >
            <template #column-active="{ item }">
                <sw-icon
                    v-if="item.active"
                    color="#37d046"
                    name="small-default-checkmark-line-medium"
                    small
                />
                <sw-icon
                    v-else
                    color="#de294c"
                    name="small-default-x-line-medium"
                    small
                />
            </template>

            <template #column-major="{ item }">
                <sw-icon
                    v-if="item.major"
                    color="#37d046"
                    name="small-default-checkmark-line-medium"
                    small
                />
                <sw-icon
                    v-else
                    color="#de294c"
                    name="small-default-x-line-medium"
                    small
                />
            </template>

            <template #column-default="{ item }">
                <sw-icon
                    v-if="item.default"
                    color="#37d046"
                    name="small-default-checkmark-line-medium"
                    small
                />
                <sw-icon
                    v-else
                    color="#de294c"
                    name="small-default-x-line-medium"
                    small
                />
            </template>

            <template #actions="{ item }">
                <sw-context-menu-item variant="danger" @click="toggle(item.flag)">
                    <template v-if="item.active">
                        {{ $tc('frosh-tools.tabs.feature-flags.deactivate') }}
                    </template>
                    <template v-else>
                        {{ $tc('frosh-tools.tabs.feature-flags.activate') }}
                    </template>
                </sw-context-menu-item>
            </template>
        </sw-data-grid>
    </sw-card>
</sw-card-view>
`;var{Component:Pe,Mixin:Fe}=Shopware;Pe.register("frosh-tools-tab-feature-flags",{template:Y,inject:["froshToolsService"],mixins:[Fe.getByName("notification")],data(){return{featureFlags:null,isLoading:!0}},created(){this.createdComponent()},computed:{columns(){return[{property:"flag",label:"frosh-tools.tabs.feature-flags.flag",rawData:!0},{property:"active",label:"frosh-tools.active",rawData:!0},{property:"description",label:"frosh-tools.tabs.feature-flags.description",rawData:!0},{property:"major",label:"frosh-tools.tabs.feature-flags.major",rawData:!0},{property:"default",label:"frosh-tools.tabs.feature-flags.default",rawData:!0}]}},methods:{async refresh(){await this.createdComponent()},async createdComponent(){this.isLoading=!0,this.featureFlags=await this.froshToolsService.getFeatureFlags(),this.isLoading=!1},async toggle(e){this.isLoading=!0,await this.froshToolsService.toggleFeatureFlag(e).then(async()=>{this.featureFlags=await this.froshToolsService.getFeatureFlags(),window.location.reload()}).catch(t=>{try{this.createNotificationError({message:t.response.data.errors[0].detail})}catch{console.error(t)}this.isLoading=!1})}}});var J=`<sw-card-view>
    <sw-card
        class="frosh-tools-tab-state-machines__state-machines-card"
        :title="$tc('frosh-tools.tabs.state-machines.title')"
        :isLoading="isLoading"
        :large="true"
    >

        <div class="frosh-tools-tab-state-machines__state-machines-card-image-wrapper">
            <img id="state_machine" class="frosh-tools-tab-state-machines__state-machines-card-image" type="image/svg+xml" src="/bundles/administration/static/img/empty-states/media-empty-state.svg" alt="State Machine" width="100%" height="auto" style="text-align:center; display:inline-block; opacity:0; "/>
        </div>
            
        <template #toolbar>
            <sw-select-field size="small" aside="true" @change="onStateMachineChange(event)" :label=" $tc('frosh-tools.tabs.state-machines.label') " :helpText="$tc('frosh-tools.tabs.state-machines.helpText')">
                <option selected="selected" value="">{{ $tc('frosh-tools.chooseStateMachine') }}</option>
                <option value="order.state">{{ $tc('frosh-tools.order') }}</option>
                <option value="order_transaction.state">{{ $tc('frosh-tools.transaction') }}</option>
                <option value="order_delivery.state">{{ $tc('frosh-tools.delivery') }}</option>
            </sw-select-field>
        </template>

    </sw-card>
</sw-card-view>
`;var{Component:Ne,Mixin:He}=Shopware;Ne.register("frosh-tools-tab-state-machines",{template:J,inject:["froshToolsService"],mixins:[He.getByName("notification")],data(){return{image:null,featureFlags:null,isLoading:!0}},created(){this.createdComponent()},computed:{columns(){return[{property:"flag",label:"frosh-tools.tabs.feature-flags.flag",rawData:!0},{property:"active",label:"frosh-tools.active",rawData:!0},{property:"description",label:"frosh-tools.tabs.feature-flags.description",rawData:!0},{property:"major",label:"frosh-tools.tabs.feature-flags.major",rawData:!0},{property:"default",label:"frosh-tools.tabs.feature-flags.default",rawData:!0}]}},methods:{createdComponent(){this.isLoading=!1},async onStateMachineChange(e){let t=await this.froshToolsService.stateMachines(e.srcElement.value),s=document.getElementById("state_machine");"svg"in t?(this.image=t.svg,s.src=this.image,s.style.opacity="1",s.style.width="100%",s.style.height="auto"):s.style.opacity="0"}}});var x=`<sw-page class="frosh-tools">
    <template slot="content">
        <sw-container>
            <sw-tabs :small="false">
                <sw-tabs-item :route="{ name: 'frosh.tools.index.index' }">
                    {{ $tc('frosh-tools.tabs.index.title') }}
                </sw-tabs-item>

{#                <sw-tabs-item :route="{ name: 'frosh.tools.index.index' }">#}
{#                    {{ $tc('frosh-tools.tabs.systemInfo.title') }}#}
{#                </sw-tabs-item>#}

                <sw-tabs-item :route="{ name: 'frosh.tools.index.cache' }">
                    {{ $tc('frosh-tools.tabs.cache.title') }}
                </sw-tabs-item>

                <sw-tabs-item :route="{ name: 'frosh.tools.index.scheduled' }">
                    {{ $tc('frosh-tools.tabs.scheduledTaskOverview.title') }}
                </sw-tabs-item>

                <sw-tabs-item :route="{ name: 'frosh.tools.index.queue' }">
                    {{ $tc('frosh-tools.tabs.queue.title') }}
                </sw-tabs-item>

                <sw-tabs-item :route="{ name: 'frosh.tools.index.logs' }">
                    {{ $tc('frosh-tools.tabs.logs.title') }}
                </sw-tabs-item>

                <sw-tabs-item :route="{ name: 'frosh.tools.index.files' }">
                    {{ $tc('frosh-tools.tabs.files.title') }}
                </sw-tabs-item>

                <sw-tabs-item :route="{ name: 'frosh.tools.index.elasticsearch' }" v-if="elasticsearchAvailable">
                    {{ $tc('frosh-tools.tabs.elasticsearch.title') }}
                </sw-tabs-item>

                <sw-tabs-item :route="{ name: 'frosh.tools.index.featureflags' }">
                    {{ $tc('frosh-tools.tabs.feature-flags.title') }}
                </sw-tabs-item>
                
                <sw-tabs-item :route="{ name: 'frosh.tools.index.statemachines' }">
                    {{ $tc('frosh-tools.tabs.state-machines.title') }}
                </sw-tabs-item>

            </sw-tabs>
        </sw-container>

        <router-view></router-view>
    </template>
</sw-page>
`;var{Component:ze}=Shopware;ze.register("frosh-tools-index",{template:x,computed:{elasticsearchAvailable(){return Shopware.State.get("context").app.config.settings?.elasticsearchEnabled||!1}}});Shopware.Service("privileges").addPrivilegeMappingEntry({category:"additional_permissions",parent:null,key:"frosh_tools",roles:{frosh_tools:{privileges:["frosh_tools:read"],dependencies:[]}}});Shopware.Module.register("frosh-tools",{type:"plugin",name:"frosh-tools.title",title:"frosh-tools.title",description:"",color:"#303A4F",icon:"default-device-dashboard",routes:{index:{component:"frosh-tools-index",path:"index",children:{index:{component:"frosh-tools-tab-index",path:"index",meta:{privilege:"frosh_tools:read",parentPath:"frosh.tools.index.index"}},cache:{component:"frosh-tools-tab-cache",path:"cache",meta:{privilege:"frosh_tools:read",parentPath:"frosh.tools.index.index"}},queue:{component:"frosh-tools-tab-queue",path:"queue",meta:{privilege:"frosh_tools:read",parentPath:"frosh.tools.index.index"}},scheduled:{component:"frosh-tools-tab-scheduled",path:"scheduled",meta:{privilege:"frosh_tools:read",parentPath:"frosh.tools.index.index"}},elasticsearch:{component:"frosh-tools-tab-elasticsearch",path:"elasticsearch",meta:{privilege:"frosh_tools:read",parentPath:"frosh.tools.index.index"}},logs:{component:"frosh-tools-tab-logs",path:"logs",meta:{privilege:"frosh_tools:read",parentPath:"frosh.tools.index.index"}},files:{component:"frosh-tools-tab-files",path:"files",meta:{privilege:"frosh_tools:read",parentPath:"frosh.tools.index.index"}},featureflags:{component:"frosh-tools-tab-feature-flags",path:"feature-flags",meta:{privilege:"frosh_tools:read",parentPath:"frosh.tools.index.index"}},statemachines:{component:"frosh-tools-tab-state-machines",path:"state-machines",meta:{privilege:"frosh_tools:read",parentPath:"frosh.tools.index.index"}}}}},settingsItem:[{group:"plugins",to:"frosh.tools.index.cache",icon:"default-action-settings",name:"frosh-tools.title"}]});})();
