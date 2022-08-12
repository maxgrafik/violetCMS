/**!
 * violetCMS – Chart
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license MIT
 */

define(function() {
    "use strict";

    function Chart(el) {

        const self = this;

        self.element = el;
        self.chart = self.createSVG(0, 0);

        el.appendChild(self.chart);
    }

    Chart.prototype.setData = function(dataSet) {

        const self = this;

        const labels = dataSet.labels;
        const data = dataSet.data;

        if (!dataSet || !labels || !data) { return; }

        const count = labels.length;

        const padding = 15;
        const gutter = 10;

        const columWidth = (self.element.offsetWidth-(2*padding)-((count-1)*gutter))/count;

        const width = self.element.offsetWidth;
        const height = 180;

        self.chart.setAttributeNS(null, "viewBox", "0 0 "+width+" "+height);
        self.chart.setAttributeNS(null, "width", width);
        self.chart.setAttributeNS(null, "height", height);

        let maxValue = 0;
        for (let i = 0; i < count; i++) {
            maxValue = Math.max(maxValue, data[i]);
        }
        const scale = (height-(2*padding)-(2*gutter))/maxValue;

        self.clearSVG(self.chart);

        self.chart.appendChild(self.createGradient());

        for (let i = 0; i < count; i++) {
            const label = labels[i];
            const value = data[i];
            const posX = padding+(i*(columWidth+gutter));
            const posY = height-(padding)-(2*gutter);

            // label text
            self.chart.appendChild(self.createText(posX+(columWidth/2), height-padding, label, "#fff"));

            if (value) {
                // value bar
                self.chart.appendChild(self.createRect(posX, posY, columWidth, value*scale));

                // value text
                const posTextY = posY-(value*scale)+(2*gutter);
                if (posTextY > (posY-gutter)) {
                    self.chart.appendChild(self.createText(posX+(columWidth/2), posY-(value*scale)-gutter, value, "#fff"));
                } else {
                    self.chart.appendChild(self.createText(posX+(columWidth/2), posY-(value*scale)+(2*gutter), value, "#a3f"));
                }
            } else {
                self.chart.appendChild( self.createRect(posX, posY, columWidth, 1) );
            }
        }

        const elms = self.chart.querySelectorAll("animate");
        /* eslint-disable-next-line no-cond-assign */
        for (let i = 0, el; el = elms[i]; i++) {
            el.beginElement();
        }
    };

    Chart.prototype.createSVG = function(width, height) {
        const svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
        svg.setAttributeNS(null, "viewBox", "0 0 "+width+" "+height);
        svg.setAttributeNS(null, "width", width);
        svg.setAttributeNS(null, "height", height);
        return svg;
    };

    Chart.prototype.createGradient = function() {
        const stop1 = document.createElementNS("http://www.w3.org/2000/svg", "stop");
        stop1.setAttributeNS(null, "offset", "0%");
        stop1.setAttributeNS(null, "style", "stop-color:#fff;stop-opacity:1");

        const stop2 = document.createElementNS("http://www.w3.org/2000/svg", "stop");
        stop2.setAttributeNS(null, "offset", "100%");
        stop2.setAttributeNS(null, "style", "stop-color:#fff;stop-opacity:0.3");

        const linearGradient = document.createElementNS("http://www.w3.org/2000/svg", "linearGradient");
        linearGradient.setAttributeNS(null, "id", "gradient");
        linearGradient.setAttributeNS(null, "x1", "0%");
        linearGradient.setAttributeNS(null, "y1", "0%");
        linearGradient.setAttributeNS(null, "x2", "0%");
        linearGradient.setAttributeNS(null, "y2", "100%");
        linearGradient.appendChild(stop1);
        linearGradient.appendChild(stop2);

        const defs = document.createElementNS("http://www.w3.org/2000/svg", "defs");
        defs.appendChild(linearGradient);

        return defs;
    };

    Chart.prototype.createRect = function(x, y, w, h) {
        const rect = document.createElementNS("http://www.w3.org/2000/svg", "rect");
        rect.setAttributeNS(null, "x", x);
        rect.setAttributeNS(null, "y", y);
        rect.setAttributeNS(null, "rx", 2);
        rect.setAttributeNS(null, "ry", 2);
        rect.setAttributeNS(null, "width", w);
        rect.setAttributeNS(null, "height", 0);
        rect.setAttributeNS(null, "fill", "url(#gradient)");

        const animHeight = document.createElementNS("http://www.w3.org/2000/svg", "animate");
        animHeight.setAttributeNS(null, "attributeName", "height");
        animHeight.setAttributeNS(null, "dur", "0.2s");
        animHeight.setAttributeNS(null, "from", "0");
        animHeight.setAttributeNS(null, "to", Math.max(1, h));
        animHeight.setAttributeNS(null, "restart", "always");
        animHeight.setAttributeNS(null, "fill", "freeze");
        rect.appendChild(animHeight);

        const animPosY = document.createElementNS("http://www.w3.org/2000/svg", "animate");
        animPosY.setAttributeNS(null, "attributeName", "y");
        animPosY.setAttributeNS(null, "dur", "0.2s");
        animPosY.setAttributeNS(null, "from", y );
        animPosY.setAttributeNS(null, "to", y-Math.max(1, h) );
        animPosY.setAttributeNS(null, "restart", "always");
        animPosY.setAttributeNS(null, "fill", "freeze");
        rect.appendChild(animPosY);

        return rect;
    };

    Chart.prototype.createText = function(x, y, content, color) {
        const text = document.createElementNS("http://www.w3.org/2000/svg", "text");
        text.setAttributeNS(null, "x", x);
        text.setAttributeNS(null, "y", y);
        text.setAttributeNS(null, "fill", color);
        text.setAttributeNS(null, "text-anchor", "middle");
        text.textContent = content;
        return text;
    };

    Chart.prototype.clearSVG = function(svg) {
        while (svg.firstChild) { svg.firstChild.remove(); }
    };

    Chart.prototype.dispose = function() {
        const self = this;
        const parent = self.chart.parentElement;
        parent && parent.removeChild(self.chart);
        self.chart = null;
    };

    return Chart;
});
