<table style="width: 100%; margin-bottom: 10px; border-collapse: collapse; border-spacing: 0;">
  <tbody>
    <tr>
      <td style="width: 35%; border: 0; padding: 0; vertical-align: top;">
        <img src="?entryPoint=attachment&amp;id=694e19197dcc35fe0" style="display: block; max-width: 100%; max-height: 80px; width: auto;">
      </td>
      <td style="width: 65%; border: 0; padding: 0; vertical-align: top; text-align: right;">
        <p style="margin: 0;">
          <span style="font-size: 20px;"><strong>{{account.name}}</strong></span><br>
          <i>
            {{#if account.billingAddressStreet}}
            {{account.billingAddressStreet}}<br>
            {{account.billingAddressPostalCode}} {{account.billingAddressCity}}, {{account.billingAddressCountry}}<br>
            {{/if}}
            {{#if account.phoneNumber}}
            Điện thoại: {{account.phoneNumber}}<br>
            {{/if}}
            {{#if account.emailAddress}}
            Email: {{account.emailAddress}}<br>
            {{/if}}
          </i>
        </p>
      </td>
    </tr>
  </tbody>
</table>

<div style="text-align: center; margin: 40px 0;">
  <h1 style="margin: 0; padding: 0;">PHIẾU GIAO HÀNG</h1>
</div>
      
<table class="no-border" style="margin-bottom: 20px;">
  <tbody>
    <tr>
      <td class="section-label" style="border:0; padding: 2px 4px; line-height: 1.2;">Số phiếu:</td>
      <td>{{name}}</td>
      <td class="section-label" style="border:0; padding: 2px 4px; line-height: 1.2;">Ngày giao:</td>
      <td>{{deliveryDate}}</td>
    </tr>
    <tr>
      <td class="section-label" style="border:0; padding: 2px 4px; line-height: 1.2;">Tổng số kiện:</td>
      <td>{{totalPackages}}</td>
      <td class="section-label" style="border:0; padding: 2px 4px; line-height: 1.2;">Đã đến kho:</td>
      <td>{{createdAt}}</td>
    </tr>
    <tr>
      <td class="section-label" style="border:0; padding: 2px 4px; line-height: 1.2;">Tổng khối lượng:</td>
      <td>{{totalWeight}} kg</td>
      <td class="section-label" style="border:0; padding: 2px 4px; line-height: 1.2;">Loại dịch vụ:</td>
      <td>{{serviceType}}</td>
    </tr>
    <tr>
      <td class="section-label" style="border:0; padding: 2px 4px; line-height: 1.2;">Tổng thể tích:</td>
      <td>{{totalVolume}} m³</td>
      <td class="section-label" style="border:0; padding: 2px 4px; line-height: 1.2;">Đơn giá giao hàng:</td>
      <td>{{unitDeliveryPriceVnd}} ₫</td>
    </tr>
  </tbody>
</table>
    
<table>
  <thead>
    <tr>
      <th class="center" style="width: 5%;">Số</th>
      <th class="center" style="width: 15%;">Mã vận đơn</th>
      <th class="center" style="width: 7%;">Khối lượng</th>
      <th class="center" style="width: 7%;">Thể tích</th>
      <th class="center" style="width: 15%;">Tổng phí giao hàng</th>
    </tr>
  </thead>
  <tbody class="order-items">
    {{#each parcels}}
    <tr>
      <td class="center">{{@index}}</td>
      <td><strong>{{name}}</strong></td>
      {{#if weight}}
      <td class="center">{{weight}}</td>
      {{else}}
      <td class="center">-</td>
      {{/if}}
      {{#if volume}}
      <td class="center">{{volume}}</td>
      {{else}}
      <td class="center">-</td>
      {{/if}}
      <td class="right">{{totalDeliveryPriceVnd}} ₫</td>
    </tr>
    {{/each}}
    </tbody>
</table>

{{#if order.notes}}
<div style="margin: 20px 0;">
  <p><strong>Ghi chú:</strong></p>
  <p>{{order.notes}}</p>
</div>
{{/if}}

<table style="margin: 20px 0;">
  <tbody>
    <tr>
      <td style="width: 40%; border:0;"></td>
      <td style="border:0;">
        <table>
          <tbody>
            <tr>
              <td class="section-label" style="border:0; padding: 2px 4px; line-height: 1.2;">Tổng phí giao hàng:</td>
              <td class="right" style="border:0; padding: 2px 4px; line-height: 1.2;">{{totalDeliveryPriceVnd}} ₫</td>
            </tr>
            <tr>
              <td class="section-label" style="border:0; padding: 2px 4px; line-height: 1.2;">Số tiền còn lại:</td>
              <td class="right" style="border:0; padding: 2px 4px; line-height: 1.2;">{{orderRemainingAmountVnd}} ₫</td>
            </tr>
            <tr>
              <td class="section-label" style="border:0; padding: 2px 4px; line-height: 1.2;">Số tiền phụ thu:</td>
              <td class="right" style="border:0; padding: 2px 4px; line-height: 1.2;">{{additionalAmountVnd}} ₫</td>
            </tr>
            <tr>
              <td class="section-label" style="border:0; padding: 2px 4px; line-height: 1.2;">Tổng số phải trả:</td>
              <td class="right" style="border:0; padding: 2px 4px; line-height: 1.2; font-weight:bold;">{{totalPayableAmountVnd}} ₫</td>
            </tr>
          </tbody>
        </table>
      </td>
    </tr>
  </tbody>
</table>

<div style="font-size: 11px; line-height: 1.3; margin: 8px 0;">
  <p><strong>Lưu ý:</strong></p>
  <ol>
    <li><strong>Đối với khách nhận hàng trực tiếp tại kho:</strong>
    Vui lòng kiểm tra đầy đủ số lượng kiện hàng theo phiếu xuất kho trước khi ký nhận. Sau khi quý khách đã ký nhận, Chúng tôi sẽ không tiếp nhận khiếu nại phát sinh liên quan đến hàng hóa gì thêm.</li>
    <li><strong>Đối với hàng hóa gửi qua chuyển phát nhanh, nhà xe,...:</strong> Quý khách vui lòng quay video quá trình mở kiện hàng và kiểm tra mã vận đơn theo phiếu xuất kho. Trong trường hợp không có video đối soát, Chúng tôi không thể xử lý các vấn đề phát sinh (thiếu, nhầm, hư hỏng...).</li>
    <li><strong>Thời gian tiếp nhận phản hồi:</strong> Mọi vấn đề liên quan đến đơn hàng vui lòng phản hồi trong vòng 24 giờ kể từ khi nhận hàng. Sau thời gian này, Chúng tôi sẽ mặc định đơn hàng đã được giao đầy đủ và không có vấn đề gì phát sinh.</li>
  </ol>
  <br>
  Rất mong nhận được sự phối hợp từ quý khách hàng để việc kiểm soát hàng hóa diễn ra thuận lợi và hiệu quả hơn. Trân trọng!
</div>

<p>
  </p><table class="no-border" style="margin: 40px 0 0 40px;"><tbody>
    <tr>
      <td>
        <p><strong>Người xuất kho</strong><br>(Ký, ghi rõ họ tên)</p>
      </td>
      <td>
        <p><strong>Người nhận hàng</strong><br>(Ký, ghi rõ họ tên)</p>
      </td>
    </tr>
  </tbody>
</table>
