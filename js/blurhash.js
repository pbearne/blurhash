import {decode} from "./../node_modules/blurhash/dist/esm/index.js";


document.addEventListener("DOMContentLoaded", function (event) {
	const images = Array.from( document.getElementsByTagName('img') );

	let srcList = [];
	for (let i = 0; i < images.length; i++) {
		let hash = images[i].getAttribute('data-blurhash');
		if( hash ){
			let h = images[i].getAttribute('height');
			let w = images[i].getAttribute('width');
			let bg = decode(hash, w, h);
			const canvas = document.createElement("canvas");
			const ctx = canvas.getContext("2d");
			const imageData = ctx.createImageData(w, h);
			imageData.data.set(bg);
			ctx.putImageData(imageData, 0, 0);
			const bgImage = canvas.toDataURL();
			images[i].style.backgroundImage = `url(${bgImage})`;
			images[i].style.backgroundRepeat = 'no-repeat';
			images[i].style.backgroundPosition = 'center';
			images[i].style.backgroundSize = 'cover';
			images[i].onload = function(){
				images[i].style.backgroundImage = ``;
			}
		}
	}
});

